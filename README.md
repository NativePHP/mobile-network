# Network Plugin for NativePHP Mobile

Network connectivity status monitoring for NativePHP Mobile applications.

## Overview

The Network API provides cross-platform network connectivity status detection, including connection type and metered status, and an event when that status changes.

## Installation

```bash
composer require nativephp/mobile-network
```

## Usage

### PHP (Livewire/Blade)

```php
use Native\Mobile\Facades\Network;

// Get current network status
$status = Network::status();

if ($status->connected) {
    echo "Connected via: " . $status->type;

    if ($status->isExpensive) {
        echo " (metered connection)";
    }
} else {
    echo "No network connection";
}
```

### JavaScript (Vue/React/Inertia)

```js
import { Network } from '#nativephp';

// Get current network status
const status = await Network.status();

if (status.connected) {
    console.log(`Connected via: ${status.type}`);

    if (status.isExpensive) {
        console.log('Warning: metered connection');
    }
} else {
    console.log('No network connection');
}
```

### Reacting to changes

`NativePHP\Network\Events\NetworkStatusChanged` fires when the device goes online or offline, moves between Wi-Fi and cellular, or becomes metered or constrained. It carries the same four fields as `status()`.

```php
use Native\Mobile\Attributes\On;
use NativePHP\Network\Events\NetworkStatusChanged;

#[On(NetworkStatusChanged::class)]
public function networkChanged(bool $connected, string $type): void
{
    $this->offline = ! $connected;
}
```

The event implements `BroadcastsGlobally`, so app-wide listeners hear it too:

```php
Event::listen(NetworkStatusChanged::class, function (NetworkStatusChanged $event) {
    if ($event->connected) {
        // Send anything queued while offline...
    }
});
```

The status at launch is the baseline and is not sent, and repeats of the same status are dropped. An event that arrives while no screen is live, such as while the app is in the background, is not replayed, so call `status()` when you need the current value.

## Response Object

The `status()` method returns an object with the following properties:

| Property | Type | Description |
|----------|------|-------------|
| `connected` | boolean | Whether device has network connectivity. On Android the network must also have reached the internet, so a Wi-Fi sign-in page (captive portal) reads `false`; see Platform Behavior |
| `type` | string | Connection type: `wifi`, `cellular`, `ethernet`, or `unknown` |
| `isExpensive` | boolean | Whether connection is metered (e.g., cellular data) |
| `isConstrained` | boolean | Whether Low Data Mode is enabled (iOS only) |
| `error` | string | Present only when the status could not be determined. The other fields are then placeholders, not an observation — treat the connection as unknown rather than offline |

## Examples

### Conditional Data Sync

```php
use Native\Mobile\Facades\Network;

public function syncData()
{
    $status = Network::status();

    if (!$status->connected) {
        // Handle no connection
        return;
    }

    if ($status->isExpensive) {
        // On cellular - sync only essential data
        $this->syncEssentialData();
    } else {
        // On WiFi - full sync
        $this->syncAllData();
    }
}
```

### JavaScript Connection Check

```js
import { Network } from '#nativephp';

async function checkBeforeDownload() {
    const status = await Network.status();

    if (!status.connected) {
        console.log('No internet connection');
        return false;
    }

    if (status.isExpensive && status.type === 'cellular') {
        console.log('On cellular data - consider warning user');
        return false;
    }

    return true;
}
```

## Platform Behavior

### Android
- Uses `ConnectivityManager` and `NetworkCapabilities`
- Follows the default network with `registerDefaultNetworkCallback`, started at launch, for `NetworkStatusChanged`
- `connected` requires `NET_CAPABILITY_VALIDATED`: Android has reached the internet through the network, the same check behind the status bar's no-internet mark. A Wi-Fi network behind a sign-in page (captive portal) or without internet reads `connected: false` with `type: wifi`, and a network that has just joined reads `connected: false` for the second or two Android takes to validate it. A network whose firewall blocks Android's connectivity check never validates, so it reads `connected: false` throughout
- A disconnect is reported after 1s, and not at all if a connected network becomes the default in that time, so a Wi-Fi to mobile data handover doesn't report a brief disconnect
- `isConstrained` is always `false` (not applicable)
- Requires `ACCESS_NETWORK_STATE` permission (added automatically)

### iOS
- Uses one long-lived `NWPathMonitor` from Network framework, started on the first call and answered from its latest path, so calls after the first cost no round trip to the network daemon
- The monitor starts at launch, and each path whose status differs from the last one is sent as `NetworkStatusChanged`
- The first call waits up to 0.5s for the monitor's first path; if none arrives it returns `error` instead of guessing `connected: false`
- `isConstrained` reflects Low Data Mode setting
- No special permissions required

## Testing

The plugin extends the NativePHP testing suite with network-specific helpers, so your app tests can fake connectivity and assert it was checked without knowing any bridge internals:

```php
use Native\Mobile\Testing\Native;

it('syncs everything on wifi', function () {
    Native::fakeBridge()->withWifi();

    Native::test(SyncButton::class)
        ->tap('Sync now')
        ->assertNetworkChecked();
});

it('warns instead of syncing when offline', function () {
    Native::fakeBridge()->withOffline();

    Native::test(SyncButton::class)
        ->tap('Sync now')
        ->assertSee('No connection');
});
```

### Helpers

- `withNetworkStatus(array $status = [])` — fake the raw response to `status()`. Accepts any of `connected`, `type`, `isExpensive`, `isConstrained`, `error` — the same fields the native bridge returns.
- `withWifi(array $extra = [])` — fake a connected, unmetered, unconstrained Wi-Fi status. Pass `$extra` to override individual fields.
- `withCellular(array $extra = [])` — fake a connected, metered cellular status.
- `withOffline(array $extra = [])` — fake a disconnected status (`type: 'unknown'`).
- `withError(string $error = 'Unknown error', array $extra = [])` — fake the native error path (e.g. an Android catch reporting failure to read connectivity state).
- `assertNetworkChecked()` — assert `status()` was called.

The helpers are available on `Native::fakeBridge()` and chain directly off `Native::test(...)`. They register automatically while running tests (requires a core with a macroable FakeBridge; on older cores they simply don't register).

Note that `status()` decodes the bridge's JSON response without the `true` flag, so it returns a `stdClass` object (`$status->type`), not an array — the same is true of the objects returned while faked.

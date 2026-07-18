# Network Plugin for NativePHP Mobile

Network connectivity status monitoring for NativePHP Mobile applications.

## Overview

The Network API provides cross-platform network connectivity status detection, including connection type and metered status.

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

## Response Object

The `status()` method returns an object with the following properties:

| Property | Type | Description |
|----------|------|-------------|
| `connected` | boolean | Whether device has network connectivity |
| `type` | string | Connection type: `wifi`, `cellular`, `ethernet`, or `unknown` |
| `isExpensive` | boolean | Whether connection is metered (e.g., cellular data) |
| `isConstrained` | boolean | Whether Low Data Mode is enabled (iOS only) |

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
- `isConstrained` is always `false` (not applicable)
- Requires `ACCESS_NETWORK_STATE` permission (added automatically)

### iOS
- Uses `NWPathMonitor` from Network framework
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

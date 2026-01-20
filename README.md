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
import { network } from '#nativephp';

// Get current network status
const status = await network.status();

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
        Dialog::toast('No internet connection');
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
import { network, dialog } from '#nativephp';

async function downloadLargeFile() {
    const status = await network.status();

    if (!status.connected) {
        dialog.toast('No internet connection');
        return;
    }

    if (status.isExpensive && status.type === 'cellular') {
        // Warn user about large download on cellular
        dialog.alert(
            'Large Download',
            'This file is 50MB. Download on cellular data?',
            ['Cancel', 'Download']
        );
        return;
    }

    // Proceed with download
    startDownload();
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

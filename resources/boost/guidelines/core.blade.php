## nativephp/network

Network connectivity status monitoring for NativePHP Mobile applications.

### Installation

```bash
composer require nativephp/network
php artisan native:plugin:register nativephp/network
```

### PHP Usage (Livewire/Blade)

Use the `Network` facade:

@verbatim
<code-snippet name="Network Status" lang="php">
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
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Conditional Data Sync" lang="php">
use Native\Mobile\Facades\Network;
use Native\Mobile\Facades\Dialog;

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
</code-snippet>
@endverbatim

### JavaScript Usage

@verbatim
<code-snippet name="Network Status in JavaScript" lang="js">
import { network, dialog } from '#nativephp';

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

// Example: Warn before large download on cellular
async function downloadLargeFile() {
    const status = await network.status();

    if (status.isExpensive && status.type === 'cellular') {
        dialog.alert(
            'Large Download',
            'This file is 50MB. Download on cellular data?',
            ['Cancel', 'Download']
        );
        return;
    }

    startDownload();
}
</code-snippet>
@endverbatim

### Available Methods

- `Network::status()` - Get current network connectivity status

### Response Properties

| Property | Type | Description |
|----------|------|-------------|
| `connected` | boolean | Whether device has network connectivity |
| `type` | string | Connection type: `wifi`, `cellular`, `ethernet`, or `unknown` |
| `isExpensive` | boolean | Whether connection is metered (e.g., cellular data) |
| `isConstrained` | boolean | Whether Low Data Mode is enabled (iOS only) |

### Platform Details

- **Android**: Uses `ConnectivityManager` and `NetworkCapabilities`
  - `isConstrained` is always `false` (not applicable)
  - Requires `ACCESS_NETWORK_STATE` permission (added automatically)
- **iOS**: Uses `NWPathMonitor` from Network framework
  - `isConstrained` reflects Low Data Mode setting
  - No special permissions required
## nativephp/network

Network connectivity status monitoring for NativePHP Mobile applications.

### PHP Usage (Livewire/Blade)

@verbatim
<code-snippet name="Network Status" lang="php">
use Native\Mobile\Facades\Network;

$status = Network::status();
if ($status) {
    echo $status->connected;      // true/false
    echo $status->type;           // "wifi", "cellular", "ethernet", or "unknown"
    echo $status->isExpensive;    // true/false (iOS only)
    echo $status->isConstrained;  // true/false (iOS only)
}
</code-snippet>
@endverbatim

### JavaScript Usage (Vue/React/Inertia)

@verbatim
<code-snippet name="Network Status in JavaScript" lang="javascript">
import { network } from '#nativephp';

const status = await network.status();
if (status) {
    console.log(status.connected);      // true/false
    console.log(status.type);           // "wifi", "cellular", "ethernet", or "unknown"
    console.log(status.isExpensive);    // true/false (iOS only)
    console.log(status.isConstrained);  // true/false (iOS only)
}
</code-snippet>
@endverbatim

### Response Properties

| Property | Type | Description |
|----------|------|-------------|
| connected | bool | Device network connection state |
| type | string | Connection type: "wifi", "cellular", "ethernet", "unknown" |
| isExpensive | bool | Metered/cellular connection (iOS only; false on Android) |
| isConstrained | bool | Low Data Mode status (iOS only; false on Android) |

### Notes

- Returns current state only; not a real-time stream
- No event system - must call directly or implement periodic checks
- Requires `network_state` permission (enabled by default)
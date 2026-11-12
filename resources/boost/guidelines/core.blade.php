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

### Reacting to changes

@verbatim
<code-snippet name="Network Status Changed" lang="php">
use Native\Mobile\Attributes\On;
use NativePHP\Network\Events\NetworkStatusChanged;

#[On(NetworkStatusChanged::class)]
public function networkChanged(bool $connected, string $type): void
{
    $this->offline = ! $connected;
}

// Or app-wide: Event::listen(NetworkStatusChanged::class, fn ($e) => ...);
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

- `status()` returns the current state; `NetworkStatusChanged` fires when it changes
- The status at launch is not sent as an event, and an event missed while the app is in the background is not replayed, so call `status()` when you need the current value
- Requires `network_state` permission (enabled by default)
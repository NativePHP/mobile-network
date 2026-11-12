<?php

namespace NativePHP\Network\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Native\Mobile\Events\Concerns\BroadcastsGlobally;

/**
 * The device's network status changed while the app was running: it went
 * online or offline, moved between Wi-Fi and cellular, or became metered or
 * constrained. The properties are the fields Network::status() returns.
 *
 * Sent from native (iOS `NWPathMonitor`, Android
 * `ConnectivityManager.registerDefaultNetworkCallback`). The status at launch
 * is the baseline and is not sent, and repeats of the same status are dropped.
 *
 * React in a component:
 *
 *     #[On(NetworkStatusChanged::class)]
 *     public function networkChanged(bool $connected, string $type): void { ... }
 *
 * …or anywhere in the app, since it implements BroadcastsGlobally:
 *
 *     Event::listen(NetworkStatusChanged::class, fn ($e) => ...);
 *
 * An event that arrives while no screen is live (the app is in the
 * background) is not replayed, so read Network::status() for the current
 * value when the app returns.
 */
class NetworkStatusChanged implements BroadcastsGlobally
{
    use Dispatchable;

    public function __construct(
        public bool $connected,
        /** 'wifi' | 'cellular' | 'ethernet' | 'unknown' */
        public string $type,
        public bool $isExpensive = false,
        public bool $isConstrained = false,
    ) {}
}

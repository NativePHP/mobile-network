<?php

namespace Tests\Fixtures;

use Illuminate\View\View;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use NativePHP\Network\Events\NetworkStatusChanged;

/** A screen that listens for NetworkStatusChanged the way an app would. */
class NetworkProbe extends NativeComponent
{
    public ?bool $connected = null;

    public string $type = '';

    #[On(NetworkStatusChanged::class)]
    public function networkChanged(bool $connected, string $type): void
    {
        $this->connected = $connected;
        $this->type = $type;
    }

    public function render(): View
    {
        return view('network-probe');
    }
}

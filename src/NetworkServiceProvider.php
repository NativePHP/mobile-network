<?php

namespace Native\Mobile\Providers;

use Illuminate\Support\ServiceProvider;
use Native\Mobile\Network;
use Native\Mobile\Providers\Testing\NetworkMacros;
use Native\Mobile\Testing\FakeBridge;

class NetworkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Network::class, function () {
            return new Network;
        });

        // Test sugar (withWifi(), assertNetworkChecked(), etc.) — only under
        // a test runner, and only on a core whose FakeBridge is macroable
        // (the method_exists guard keeps older v4 and v3 cores fatal-free).
        if ($this->app->runningUnitTests()
            && class_exists(FakeBridge::class)
            && method_exists(FakeBridge::class, 'macro')) {
            NetworkMacros::register();
        }
    }
}

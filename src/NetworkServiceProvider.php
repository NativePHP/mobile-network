<?php

namespace Native\Mobile\Providers;

use Illuminate\Support\ServiceProvider;
use Native\Mobile\Network;

class NetworkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Network::class, function () {
            return new Network;
        });
    }
}

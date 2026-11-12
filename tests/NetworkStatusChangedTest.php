<?php

/**
 * NetworkStatusChanged as the native side sends it: the payload keys are the
 * Network.Status fields, and they must bind to the event's constructor so the
 * event reaches a screen's #[On] listener and app-wide listeners.
 */

use Illuminate\Support\Facades\Event;
use Native\Mobile\Testing\Native;
use NativePHP\Network\Events\NetworkStatusChanged;
use Tests\Fixtures\NetworkProbe;
use Tests\TestCase;

uses(TestCase::class);

it('delivers an offline status to the screen and app-wide', function () {
    Event::fake([NetworkStatusChanged::class]);

    Native::test(NetworkProbe::class)
        ->assertSee('Waiting')
        ->emitNative(NetworkStatusChanged::class, [
            'connected' => false,
            'type' => 'unknown',
            'isExpensive' => false,
            'isConstrained' => false,
        ])
        ->assertSet('connected', false)
        ->assertSee('Offline unknown');

    Event::assertDispatched(
        NetworkStatusChanged::class,
        fn (NetworkStatusChanged $event) => $event->connected === false && $event->type === 'unknown',
    );
});

it('carries every Network.Status field', function () {
    Event::fake([NetworkStatusChanged::class]);

    Native::test(NetworkProbe::class)
        ->emitNative(NetworkStatusChanged::class, [
            'connected' => true,
            'type' => 'cellular',
            'isExpensive' => true,
            'isConstrained' => true,
        ])
        ->assertSee('Online cellular');

    Event::assertDispatched(
        NetworkStatusChanged::class,
        fn (NetworkStatusChanged $event) => $event->connected
            && $event->type === 'cellular'
            && $event->isExpensive
            && $event->isConstrained,
    );
});

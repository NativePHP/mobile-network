<?php

/**
 * The network test vocabulary this plugin registers on the FakeBridge
 * (withNetworkStatus / withWifi / withCellular / withOffline / withError /
 * assertNetworkChecked) — the sugar app developers use instead of raw
 * bridge method strings.
 *
 * Skipped on cores whose FakeBridge predates macro support.
 */

use Native\Mobile\Network;
use Native\Mobile\Testing\FakeBridge;
use Native\Mobile\Testing\Native;
use PHPUnit\Framework\AssertionFailedError;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (! method_exists(FakeBridge::class, 'macro')) {
        $this->markTestSkipped('This core\'s FakeBridge does not support macros.');
    }

    $this->bridge = Native::fakeBridge();
});

describe('withNetworkStatus()', function () {
    it('scripts a raw response that decodes through status()', function () {
        $this->bridge->withNetworkStatus([
            'connected' => true,
            'type' => 'ethernet',
            'isExpensive' => false,
            'isConstrained' => false,
        ]);

        $status = (new Network)->status();

        expect($status)->toBeObject();
        expect($status->connected)->toBeTrue();
        expect($status->type)->toBe('ethernet');
        expect($status->isExpensive)->toBeFalse();
        expect($status->isConstrained)->toBeFalse();
    });

    it('defaults to an empty scripted response', function () {
        $this->bridge->withNetworkStatus();

        $status = (new Network)->status();

        expect($status)->toBeObject();
        expect(get_object_vars($status))->toBe([]);
    });
});

describe('withWifi()', function () {
    it('fakes a connected, unmetered, unconstrained wifi status', function () {
        $this->bridge->withWifi();

        $status = (new Network)->status();

        expect($status->connected)->toBeTrue();
        expect($status->type)->toBe('wifi');
        expect($status->isExpensive)->toBeFalse();
        expect($status->isConstrained)->toBeFalse();
    });

    it('allows overriding fields', function () {
        $this->bridge->withWifi(['isConstrained' => true]);

        $status = (new Network)->status();

        expect($status->type)->toBe('wifi');
        expect($status->isConstrained)->toBeTrue();
    });
});

describe('withCellular()', function () {
    it('fakes a connected, metered cellular status', function () {
        $this->bridge->withCellular();

        $status = (new Network)->status();

        expect($status->connected)->toBeTrue();
        expect($status->type)->toBe('cellular');
        expect($status->isExpensive)->toBeTrue();
        expect($status->isConstrained)->toBeFalse();
    });

    it('allows overriding fields', function () {
        $this->bridge->withCellular(['isConstrained' => true]);

        $status = (new Network)->status();

        expect($status->isExpensive)->toBeTrue();
        expect($status->isConstrained)->toBeTrue();
    });
});

describe('withOffline()', function () {
    it('fakes a disconnected status with unknown type', function () {
        $this->bridge->withOffline();

        $status = (new Network)->status();

        expect($status->connected)->toBeFalse();
        expect($status->type)->toBe('unknown');
        expect($status->isExpensive)->toBeFalse();
        expect($status->isConstrained)->toBeFalse();
    });
});

describe('withError()', function () {
    it('fakes the native error path with a default message', function () {
        $this->bridge->withError();

        $status = (new Network)->status();

        expect($status->connected)->toBeFalse();
        expect($status->type)->toBe('error');
        expect($status->error)->toBe('Unknown error');
    });

    it('accepts a custom error message', function () {
        $this->bridge->withError('permission denied');

        $status = (new Network)->status();

        expect($status->type)->toBe('error');
        expect($status->error)->toBe('permission denied');
    });
});

describe('assertNetworkChecked()', function () {
    it('passes after status() was called', function () {
        $this->bridge->withWifi();

        (new Network)->status();

        $this->bridge->assertNetworkChecked();
    });

    it('fails when status() was never called', function () {
        expect(fn () => $this->bridge->assertNetworkChecked())
            ->toThrow(AssertionFailedError::class);
    });
});

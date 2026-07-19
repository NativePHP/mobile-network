<?php

/**
 * Contract tests for the Network facade (which ships in nativephp/mobile)
 * against THIS plugin's bridge function, driven through the NativePHP
 * FakeBridge. They pin the PHP → native contract: calling the facade fires
 * the right bridge function with the right payload and decodes native
 * responses as callers expect. Requires nativephp/mobile, so this file is
 * bound to the Testbench TestCase.
 *
 * Network::status() decodes the raw JSON response with json_decode($result)
 * (no `true` flag), so a successful response is a stdClass object, not an
 * array — every assertion below reflects that.
 *
 * Run with: ./test-plugins.sh --element network
 */

use Native\Mobile\Network;
use Native\Mobile\Testing\Native;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->bridge = Native::fakeBridge();
});

describe('status()', function () {
    it('fires Network.Status with an empty payload', function () {
        $this->bridge->respondTo('Network.Status', [
            'connected' => true,
            'type' => 'wifi',
            'isExpensive' => false,
            'isConstrained' => false,
        ]);

        (new Network)->status();

        $this->bridge->assertCalled('Network.Status', function (array $p) {
            expect($p)->toBe([]);

            return true;
        });
    });

    it('calls Network.Status exactly once per status() call', function () {
        (new Network)->status();

        $this->bridge->assertCalledTimes('Network.Status', 1);
    });

    it('fires a fresh bridge call on every invocation', function () {
        (new Network)->status();
        (new Network)->status();
        (new Network)->status();

        $this->bridge->assertCalledTimes('Network.Status', 3);
    });

    it('decodes a connected wifi status', function () {
        $this->bridge->respondTo('Network.Status', [
            'connected' => true,
            'type' => 'wifi',
            'isExpensive' => false,
            'isConstrained' => false,
        ]);

        $status = (new Network)->status();

        expect($status)->toBeObject();
        expect($status->connected)->toBeTrue();
        expect($status->type)->toBe('wifi');
        expect($status->isExpensive)->toBeFalse();
        expect($status->isConstrained)->toBeFalse();
    });

    it('decodes a connected cellular status as expensive', function () {
        $this->bridge->respondTo('Network.Status', [
            'connected' => true,
            'type' => 'cellular',
            'isExpensive' => true,
            'isConstrained' => false,
        ]);

        $status = (new Network)->status();

        expect($status->connected)->toBeTrue();
        expect($status->type)->toBe('cellular');
        expect($status->isExpensive)->toBeTrue();
    });

    it('decodes a connected ethernet status', function () {
        $this->bridge->respondTo('Network.Status', [
            'connected' => true,
            'type' => 'ethernet',
            'isExpensive' => false,
            'isConstrained' => false,
        ]);

        $status = (new Network)->status();

        expect($status->connected)->toBeTrue();
        expect($status->type)->toBe('ethernet');
    });

    it('decodes a disconnected status with unknown type', function () {
        $this->bridge->respondTo('Network.Status', [
            'connected' => false,
            'type' => 'unknown',
            'isExpensive' => false,
            'isConstrained' => false,
        ]);

        $status = (new Network)->status();

        expect($status->connected)->toBeFalse();
        expect($status->type)->toBe('unknown');
    });

    it('decodes isConstrained when Low Data Mode is enabled (iOS)', function () {
        $this->bridge->respondTo('Network.Status', [
            'connected' => true,
            'type' => 'cellular',
            'isExpensive' => true,
            'isConstrained' => true,
        ]);

        $status = (new Network)->status();

        expect($status->isConstrained)->toBeTrue();
    });

    it('decodes an error response reported by native (Android catch path)', function () {
        $this->bridge->respondTo('Network.Status', [
            'connected' => false,
            'type' => 'error',
            'isExpensive' => false,
            'isConstrained' => false,
            'error' => 'Unknown error',
        ]);

        $status = (new Network)->status();

        expect($status)->not->toBeNull();
        expect($status->connected)->toBeFalse();
        expect($status->type)->toBe('error');
        expect($status->error)->toBe('Unknown error');
    });

    it('exposes only the properties present in a partial response', function () {
        // e.g. a stubbed/older native build that only reports connectivity.
        $this->bridge->respondTo('Network.Status', '{"connected":true}');

        $status = (new Network)->status();

        expect($status)->toBeObject();
        expect($status->connected)->toBeTrue();
        expect(property_exists($status, 'type'))->toBeFalse();
        expect(property_exists($status, 'isExpensive'))->toBeFalse();
        expect(property_exists($status, 'isConstrained'))->toBeFalse();
    });

    it('returns an object with no properties for an empty JSON object response', function () {
        $this->bridge->respondTo('Network.Status', '{}');

        $status = (new Network)->status();

        expect($status)->toBeObject();
        expect(get_object_vars($status))->toBe([]);
    });

    it('returns null when nothing is scripted for the bridge call', function () {
        $status = (new Network)->status();

        $this->bridge->assertCalled('Network.Status');
        expect($status)->toBeNull();
    });

    it('returns null when the bridge responds with an empty string', function () {
        $this->bridge->respondTo('Network.Status', '');

        expect((new Network)->status())->toBeNull();
    });

    it('returns null when the bridge responds with the string "0"', function () {
        // PHP's `if ($result)` treats the string "0" as falsy, so the
        // facade short-circuits before ever calling json_decode().
        $this->bridge->respondTo('Network.Status', '0');

        expect((new Network)->status())->toBeNull();
    });

    it('returns null when the bridge responds with literal JSON null', function () {
        $this->bridge->respondTo('Network.Status', 'null');

        expect((new Network)->status())->toBeNull();
    });

    it('returns null when the bridge responds with malformed JSON', function () {
        $this->bridge->respondTo('Network.Status', '{not valid json');

        expect((new Network)->status())->toBeNull();
    });

    it('resolves the response dynamically via a closure', function () {
        $this->bridge->respondTo('Network.Status', function (array $params) {
            expect($params)->toBe([]);

            return ['connected' => true, 'type' => 'wifi', 'isExpensive' => false, 'isConstrained' => false];
        });

        $status = (new Network)->status();

        expect($status->connected)->toBeTrue();
        expect($status->type)->toBe('wifi');
    });

    it('does not fire any other bridge method', function () {
        (new Network)->status();

        $this->bridge->assertNotCalled('Network.WriteText');
        $this->bridge->assertNotCalled('Network.Configure');
    });
});

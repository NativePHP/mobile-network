<?php

namespace Native\Mobile\Providers\Testing;

use Native\Mobile\Testing\FakeBridge;

/**
 * Network test vocabulary for the NativePHP testing suite, registered as
 * FakeBridge macros so app tests read in network terms instead of raw
 * bridge method strings:
 *
 *     Native::fakeBridge()->withWifi();
 *
 *     Native::test(SyncButton::class)
 *         ->tap('sync')
 *         ->assertNetworkChecked();
 *
 * Network::status() decodes the raw JSON response with json_decode($result)
 * (no `true` flag), so a scripted response comes back to the app as a
 * stdClass, not an array — the shape mirrors the real bridge's fields:
 * connected, type (wifi/cellular/ethernet/unknown/error), isExpensive,
 * isConstrained, and error (only on the error path).
 *
 * Registered by NetworkServiceProvider when the app is running unit tests
 * on a core whose FakeBridge supports macros.
 */
class NetworkMacros
{
    public static function register(): void
    {
        /**
         * Fake the response to Network::status(). Pass the raw response
         * shape — connected, type, isExpensive, isConstrained, error — or
         * reach for one of the convenience helpers below for the common
         * cases.
         */
        FakeBridge::macro('withNetworkStatus', function (array $status = []) {
            // status() decodes with json_decode() (objects, not arrays), and
            // an empty PHP array would JSON-encode to "[]" — a list, decoding
            // back to an array and tripping status()'s ?object return type.
            // Emit a literal "{}" for the empty case so it stays an object.
            return $this->respondTo('Network.Status', $status === [] ? '{}' : $status);
        });

        /** Fake a connected Wi-Fi status (unmetered, unconstrained). */
        FakeBridge::macro('withWifi', function (array $extra = []) {
            return $this->withNetworkStatus(array_merge([
                'connected' => true,
                'type' => 'wifi',
                'isExpensive' => false,
                'isConstrained' => false,
            ], $extra));
        });

        /** Fake a connected cellular status (metered by default). */
        FakeBridge::macro('withCellular', function (array $extra = []) {
            return $this->withNetworkStatus(array_merge([
                'connected' => true,
                'type' => 'cellular',
                'isExpensive' => true,
                'isConstrained' => false,
            ], $extra));
        });

        /** Fake a disconnected status — no network at all. */
        FakeBridge::macro('withOffline', function (array $extra = []) {
            return $this->withNetworkStatus(array_merge([
                'connected' => false,
                'type' => 'unknown',
                'isExpensive' => false,
                'isConstrained' => false,
            ], $extra));
        });

        /**
         * Fake the native error path (e.g. an Android catch reporting
         * failure to read connectivity state).
         */
        FakeBridge::macro('withError', function (string $error = 'Unknown error', array $extra = []) {
            return $this->withNetworkStatus(array_merge([
                'connected' => false,
                'type' => 'error',
                'isExpensive' => false,
                'isConstrained' => false,
                'error' => $error,
            ], $extra));
        });

        /** Assert the network status was checked (status()). */
        FakeBridge::macro('assertNetworkChecked', function () {
            return $this->assertCalled('Network.Status');
        });
    }
}

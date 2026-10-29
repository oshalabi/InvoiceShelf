<?php

use App\Support\Net\BlockedUrlException;
use App\Support\Net\PrivateNetworkGuard;

test('blockedReason flags private, loopback, reserved and bad-scheme URLs', function (string $url) {
    expect(PrivateNetworkGuard::blockedReason($url))->not->toBeNull();
})->with([
    'loopback v4' => 'http://127.0.0.1',
    'loopback v4 with port/path' => 'http://127.0.0.1:8080/v1/chat',
    'rfc1918 10/8' => 'http://10.0.0.1',
    'rfc1918 10/8 upper' => 'http://10.255.255.255/models',
    'rfc1918 172.16/12' => 'http://172.16.0.5',
    'rfc1918 172.31' => 'https://172.31.255.255',
    'rfc1918 192.168/16' => 'http://192.168.1.1',
    'link-local / cloud metadata' => 'http://169.254.169.254/latest/meta-data/',
    'unspecified 0.0.0.0' => 'http://0.0.0.0',
    'cgnat 100.64/10' => 'http://100.64.0.1',
    'broadcast' => 'http://255.255.255.255',
    'loopback v6' => 'http://[::1]',
    'ula v6' => 'http://[fd00::1]',
    'link-local v6' => 'http://[fe80::1]',
    'ipv4-mapped v6' => 'http://[::ffff:10.0.0.1]',
    'localhost' => 'http://localhost',
    'bad scheme ftp' => 'ftp://1.1.1.1',
    'bad scheme file' => 'file:///etc/passwd',
    'no scheme or host' => 'justsometext',
]);

test('blockedReason allows public addresses and fails open on unresolvable hosts', function (string $url) {
    expect(PrivateNetworkGuard::blockedReason($url))->toBeNull();
})->with([
    'cloudflare dns literal' => 'https://1.1.1.1',
    'google dns literal with path' => 'https://8.8.8.8/v1/models?x=1',
    'public literal' => 'http://93.184.216.34',
    'unresolvable .invalid (fail-open)' => 'https://surely-not-a-real-host.invalid/api',
]);

test('blockedReason returns null for an empty string', function () {
    expect(PrivateNetworkGuard::blockedReason(''))->toBeNull();
    expect(PrivateNetworkGuard::blockedReason('   '))->toBeNull();
});

test('assertAllowed throws on a blocked URL', function () {
    expect(fn () => PrivateNetworkGuard::assertAllowed('http://169.254.169.254'))
        ->toThrow(BlockedUrlException::class);
});

test('assertAllowed is silent on a public URL', function () {
    PrivateNetworkGuard::assertAllowed('https://1.1.1.1');

    expect(true)->toBeTrue();
});

test('ipIsBlocked classifies individual addresses', function () {
    expect(PrivateNetworkGuard::ipIsBlocked('10.0.0.1'))->toBeTrue();
    expect(PrivateNetworkGuard::ipIsBlocked('169.254.169.254'))->toBeTrue();
    expect(PrivateNetworkGuard::ipIsBlocked('::1'))->toBeTrue();
    expect(PrivateNetworkGuard::ipIsBlocked('fd00::1'))->toBeTrue();
    expect(PrivateNetworkGuard::ipIsBlocked('::ffff:192.168.0.1'))->toBeTrue();

    expect(PrivateNetworkGuard::ipIsBlocked('1.1.1.1'))->toBeFalse();
    expect(PrivateNetworkGuard::ipIsBlocked('2606:4700:4700::1111'))->toBeFalse();
});

test('ipInCidr handles v4 boundaries and rejects cross-family comparisons', function () {
    expect(PrivateNetworkGuard::ipInCidr('172.16.0.0', '172.16.0.0/12'))->toBeTrue();
    expect(PrivateNetworkGuard::ipInCidr('172.31.255.255', '172.16.0.0/12'))->toBeTrue();
    expect(PrivateNetworkGuard::ipInCidr('172.32.0.0', '172.16.0.0/12'))->toBeFalse();

    // v6 address against a v4 CIDR must never match.
    expect(PrivateNetworkGuard::ipInCidr('::1', '10.0.0.0/8'))->toBeFalse();
});

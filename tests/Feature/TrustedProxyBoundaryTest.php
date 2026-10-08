<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TrustedProxyBoundaryTest extends TestCase
{
    #[DataProvider('connections')]
    public function test_forwarded_identity_requires_an_explicitly_trusted_connection(?string $proxies, string $remote, string $expectedIp, string $expectedHost, bool $expectedSecure, string $host = 'shop.example'): void
    {
        if ($proxies !== null) {
            config(['trustedproxy.proxies' => $proxies]);
        }
        Route::get('/_test/proxy-boundary', fn (Request $request) => [
            'ip' => $request->ip(),
            'host' => $request->getHost(),
            'secure' => $request->isSecure(),
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => $remote, 'HTTPS' => 'on', 'SERVER_PORT' => 443])
            ->get('https://'.$host.'/_test/proxy-boundary', [
                'X-Forwarded-For' => '198.51.100.20',
                'X-Forwarded-Host' => 'forwarded.example',
                'X-Forwarded-Proto' => 'http',
            ])
            ->assertOk()
            ->assertExactJson(['ip' => $expectedIp, 'host' => $expectedHost, 'secure' => $expectedSecure]);
    }

    public static function connections(): array
    {
        return [
            'direct connection ignores forged headers' => [null, '203.0.113.10', '203.0.113.10', 'shop.example', true],
            'hosting suffix cannot enable wildcard trust' => [null, '203.0.113.10', '203.0.113.10', 'spoof.on-forge.com', true, 'spoof.on-forge.com'],
            'untrusted connection ignores forged headers' => ['10.0.0.0/24', '10.0.1.10', '10.0.1.10', 'shop.example', true],
            'configured proxy forwards identity' => ['10.0.0.0/24', '10.0.0.10', '198.51.100.20', 'forwarded.example', false],
        ];
    }
}

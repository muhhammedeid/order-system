<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('responses')]
    public function test_application_responses_carry_browser_security_policy(string $path, int $status): void
    {
        $this->assertSecurityPolicy($this->get($path)->assertStatus($status));
    }

    public function test_maintenance_response_retains_browser_security_policy(): void
    {
        config(['app.maintenance.driver' => 'cache', 'app.maintenance.store' => 'array']);
        $maintenance = $this->app->maintenanceMode();
        $maintenance->activate(['status' => 503]);

        try {
            $this->assertSecurityPolicy($this->get('/catalog')->assertStatus(503));
        } finally {
            $maintenance->deactivate();
        }
    }

    public function test_rejected_oversized_request_retains_browser_security_policy(): void
    {
        if ((int) ini_get('post_max_size') === 0) {
            $this->markTestSkipped('This PHP runtime has no request-size limit.');
        }

        $response = $this->withServerVariables(['CONTENT_LENGTH' => (string) PHP_INT_MAX])
            ->post('/checkout')->assertStatus(413);

        $this->assertSecurityPolicy($response);
    }

    private function assertSecurityPolicy(TestResponse $response): void
    {
        $response
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public static function responses(): array
    {
        return [
            'health' => ['/up', 200],
            'storefront' => ['/catalog', 200],
            'admin login' => ['/admin/login', 200],
            'authentication redirect' => ['/admin', 302],
            'missing route' => ['/missing-security-test-page', 404],
        ];
    }
}

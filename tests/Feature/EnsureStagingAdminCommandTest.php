<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EnsureStagingAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    private const ENV_KEYS = [
        'STAGING_ADMIN_NAME',
        'STAGING_ADMIN_EMAIL',
        'STAGING_ADMIN_PASSWORD',
    ];

    protected function tearDown(): void
    {
        foreach (self::ENV_KEYS as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }

        parent::tearDown();
    }

    private function setStagingEnv(string $email, string $password, ?string $name = null): void
    {
        $values = ['STAGING_ADMIN_EMAIL' => $email, 'STAGING_ADMIN_PASSWORD' => $password];

        if ($name !== null) {
            $values['STAGING_ADMIN_NAME'] = $name;
        }

        foreach ($values as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    public function test_command_skips_when_credentials_are_not_configured(): void
    {
        $this->artisan('staging:admin')->assertExitCode(0);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_command_creates_admin_when_absent(): void
    {
        $this->setStagingEnv('staging-admin@example.com', 'secret-staging-password', 'Staging Owner');

        $this->artisan('staging:admin')->assertExitCode(0);

        $admin = User::query()->where('email', 'staging-admin@example.com')->first();

        $this->assertNotNull($admin);
        $this->assertSame('Staging Owner', $admin->name);
        $this->assertTrue(Hash::check('secret-staging-password', $admin->password));
    }

    public function test_command_keeps_existing_admin_password_unchanged(): void
    {
        User::factory()->create([
            'email' => 'staging-admin@example.com',
            'password' => 'original-password',
        ]);

        $this->setStagingEnv('staging-admin@example.com', 'different-password');

        $this->artisan('staging:admin')->assertExitCode(0);

        $this->assertDatabaseCount('users', 1);

        $admin = User::query()->where('email', 'staging-admin@example.com')->first();

        $this->assertTrue(Hash::check('original-password', $admin->password));
        $this->assertFalse(Hash::check('different-password', $admin->password));
    }

    public function test_command_refuses_to_run_in_production(): void
    {
        $this->setStagingEnv('staging-admin@example.com', 'secret-staging-password');

        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('staging:admin')->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
    }
}

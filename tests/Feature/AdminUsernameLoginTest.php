<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminUsernameLoginTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('identifiers')]
    public function test_admin_can_sign_in_with_name_or_email(string $identifier): void
    {
        $admin = User::factory()->create(['name' => 'admin', 'email' => 'admin@example.com']);
        User::factory()->create(['name' => 'Admin', 'password' => 'other-password']);

        Livewire::test(Login::class)
            ->fillForm(['email' => $identifier, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertAuthenticatedAs($admin);
    }

    public static function identifiers(): array
    {
        return [['admin'], [' admin '], ['admin@example.com']];
    }

    #[DataProvider('invalidCredentials')]
    public function test_invalid_or_ambiguous_username_does_not_authenticate(string $identifier, string $password, bool $duplicate): void
    {
        User::factory()->create(['name' => 'admin']);

        if ($duplicate) {
            User::factory()->create(['name' => 'admin']);
        }

        Livewire::test(Login::class)
            ->fillForm(['email' => $identifier, 'password' => $password])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public static function invalidCredentials(): array
    {
        return [
            'wrong password' => ['admin', 'incorrect', false],
            'unknown user' => ['missing', 'password', false],
            'duplicate name' => ['admin', 'password', true],
        ];
    }

    public function test_live_login_route_uses_a_username_capable_field(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('اسم المستخدم أو البريد الإلكتروني')
            ->assertSee('autocomplete="username"', false);
    }
}

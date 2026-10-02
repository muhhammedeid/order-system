<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class EnsureStagingAdmin extends Command
{
    protected $signature = 'staging:admin';

    protected $description = 'Create the staging admin user from environment variables if it does not exist';

    /**
     * Idempotent staging bootstrap. The admin is only created when missing;
     * an existing account is never modified, so a container cold start can
     * never reset a changed password. Credentials are read from environment
     * variables and are never printed or hard-coded.
     */
    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to bootstrap a staging admin in production.');

            return self::FAILURE;
        }

        $email = trim((string) env('STAGING_ADMIN_EMAIL'));
        $password = (string) env('STAGING_ADMIN_PASSWORD');

        if ($email === '' || $password === '') {
            $this->info('Staging admin variables are not set; skipping staging admin bootstrap.');

            return self::SUCCESS;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->info("Staging admin {$email} already exists; password left unchanged.");

            return self::SUCCESS;
        }

        $name = trim((string) env('STAGING_ADMIN_NAME')) ?: 'Staging Admin';

        User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $this->info("Staging admin {$email} created.");

        return self::SUCCESS;
    }
}

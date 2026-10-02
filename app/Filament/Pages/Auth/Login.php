<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label(__('admin.login.identifier'))
            ->required()
            ->maxLength(255)
            ->autocomplete('username')
            ->autofocus();
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        $identifier = trim($data['email']);
        $email = $identifier;

        if (! filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            // Database collation may equate Admin and admin; match the entered name exactly.
            $matches = User::query()->where('name', $identifier)->get(['name', 'email'])
                ->whereStrict('name', $identifier)->pluck('email');
            $email = $matches->count() === 1 ? $matches->first() : null;
        }

        return ['email' => $email, 'password' => $data['password']];
    }
}

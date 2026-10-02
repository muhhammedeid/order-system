<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use SensitiveParameter;

class DemoLogin extends Login
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label(__('demo.username'))
            ->required()
            ->autocomplete('username')
            ->autofocus();
    }

    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        if (! config('demo.enabled') || $data['email'] !== 'admin') {
            $this->throwFailureValidationException();
        }

        return [
            'email' => config('demo.admin_email'),
            'password' => $data['password'],
        ];
    }
}

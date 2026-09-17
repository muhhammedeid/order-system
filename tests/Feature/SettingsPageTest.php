<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_loads_stored_whatsapp_number(): void
    {
        Setting::set('whatsapp_number', '201234567890');

        Livewire::test(\App\Filament\Pages\Settings::class)
            ->assertSet('data.whatsapp_number', '201234567890');
    }

    public function test_settings_page_saves_whatsapp_number(): void
    {
        Livewire::test(\App\Filament\Pages\Settings::class)
            ->set('data.whatsapp_number', '201234567890')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('201234567890', Setting::get('whatsapp_number'));

        Livewire::test(\App\Filament\Pages\Settings::class)
            ->assertSet('data.whatsapp_number', '201234567890');
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_defaults_to_arabic_and_rtl(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'ar')
                ->where('direction', 'rtl')
                ->where('translations.nav.home', 'الرئيسية'));
    }

    public function test_language_can_switch_to_english_and_back_to_arabic(): void
    {
        $this->from('/')->post('/locale/en')
            ->assertStatus(303)
            ->assertRedirect('/');

        $this->get('/')
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('dir="ltr"', false)
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'en')
                ->where('direction', 'ltr')
                ->where('translations.nav.home', 'Home'));

        $this->from('/')->post('/locale/ar')
            ->assertStatus(303)
            ->assertRedirect('/');

        $this->get('/')->assertSee('lang="ar"', false);
    }

    public function test_unknown_locale_is_rejected(): void
    {
        $this->post('/locale/fr')->assertNotFound();
    }
}

<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class Settings extends Page
{
    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?int $navigationSort = 90;

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    /**
     * @property-read Schema $form
     */
    public function mount(): void
    {
        $this->form->fill([
            'whatsapp_number' => Setting::get('whatsapp_number'),
            'owner_whatsapp_number' => Setting::get('owner_whatsapp_number'),
            'whatsapp_customer_notifications_enabled' => Setting::whatsappCustomerNotificationsEnabled(),
            'whatsapp_manager_notifications_enabled' => Setting::whatsappManagerNotificationsEnabled(),
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Toggle::make('whatsapp_customer_notifications_enabled')
                ->label(__('admin.settings.whatsapp_customer_notifications_enabled'))
                ->helperText(__('admin.settings.automatic_notifications_hint'))
                ->columnSpanFull(),
            Toggle::make('whatsapp_manager_notifications_enabled')
                ->label(__('admin.settings.whatsapp_manager_notifications_enabled'))
                ->helperText(__('admin.settings.automatic_notifications_hint'))
                ->columnSpanFull(),
            TextInput::make('whatsapp_number')
                ->label(__('admin.settings.whatsapp_number'))
                ->hint(__('admin.settings.whatsapp_hint'))
                ->required()
                ->maxLength(32)
                ->regex('/^\+?[0-9\s\-]+$/')
                ->columnSpanFull(),
            TextInput::make('owner_whatsapp_number')
                ->label(__('admin.settings.owner_whatsapp_number'))
                ->hint(__('admin.settings.owner_whatsapp_hint'))
                ->maxLength(32)
                ->regex('/^\+?[0-9\s\-]+$/')
                ->columnSpanFull(),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('whatsapp_number', trim($data['whatsapp_number']));

        $owner = $data['owner_whatsapp_number'] ?? null;
        Setting::set('owner_whatsapp_number', filled($owner) ? trim((string) $owner) : null);

        Setting::set('whatsapp_customer_notifications_enabled', $data['whatsapp_customer_notifications_enabled'] ? '1' : '0');
        Setting::set('whatsapp_manager_notifications_enabled', $data['whatsapp_manager_notifications_enabled'] ? '1' : '0');

        Notification::make()
            ->title(__('admin.settings.saved'))
            ->success()
            ->send();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label(__('admin.settings.save'))
                                ->submit('save'),
                        ]),
                    ]),
            ]);
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.general_settings');
    }

    public function getTitle(): string
    {
        return __('admin.navigation.general_settings');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.settings');
    }
}

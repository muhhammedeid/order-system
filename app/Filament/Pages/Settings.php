<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
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
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('whatsapp_number')
                ->label('WhatsApp Number')
                ->hint('International format, e.g. 201234567890 — digits only')
                ->required()
                ->maxLength(32)
                ->regex('/^\+?[0-9\s\-]+$/')
                ->columnSpanFull(),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('whatsapp_number', trim($data['whatsapp_number']));

        Notification::make()
            ->title('Settings saved')
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
                                ->label('Save')
                                ->submit('save'),
                        ]),
                    ]),
            ]);
    }

    public static function getNavigationLabel(): string
    {
        return 'Settings';
    }
}

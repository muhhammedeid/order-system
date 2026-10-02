<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Filament\Concerns\HasExcelExport;
use App\Models\ProductVariant;
use App\Models\VariantColor;
use App\Models\VariantSize;
use App\Support\Exports\ProductVariantsExport;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

class VariantsRelationManager extends RelationManager
{
    use HasExcelExport;

    protected static string $relationship = 'variants';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament.variants.title');
    }

    public static function colorOptions(?ProductVariant $record): array
    {
        return self::options(VariantColor::query(), $record?->color);
    }

    public static function sizeOptions(?ProductVariant $record): array
    {
        return self::options(VariantSize::query(), $record?->size);
    }

    protected static function options($query, ?string $currentValue): array
    {
        $options = $query
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'name')
            ->all();

        if (filled($currentValue) && ! array_key_exists($currentValue, $options)) {
            $options[$currentValue] = $currentValue;
        }

        return $options;
    }

    protected function generateVariantsAction(): Action
    {
        return Action::make('generateVariants')
            ->label(__('filament.variants.generate'))
            ->icon(Heroicon::OutlinedSparkles)
            ->modalHeading(__('filament.variants.generate_heading'))
            ->modalDescription(__('filament.variants.generate_description'))
            ->modalSubmitActionLabel(__('filament.variants.generate_submit'))
            ->form([
                Repeater::make('colors')
                    ->label(__('filament.variants.colors_quantities'))
                    ->addActionLabel(__('filament.variants.add_color'))
                    ->defaultItems(1)
                    ->minItems(1)
                    ->columns(2)
                    ->schema([
                        Select::make('color')
                            ->label(__('filament.fields.color'))
                            ->searchable()
                            ->required()
                            ->options(fn (): array => self::colorOptions(null)),
                        TextInput::make('quantity')
                            ->label(__('filament.variants.default_quantity'))
                            ->default(50)
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(ProductVariant::MAX_QUANTITY)
                            ->required(),
                    ]),
            ])
            ->action(function (array $data): void {
                $sizes = VariantSize::activeNames();

                if (count($sizes) !== ProductVariant::DEFAULT_SIZE_COUNT) {
                    Notification::make()
                        ->title(__('filament.variants.invalid_sizes_title'))
                        ->body(__('filament.variants.invalid_sizes_body'))
                        ->danger()
                        ->send();

                    return;
                }

                $result = ProductVariant::generateMissing(
                    $this->getOwnerRecord(),
                    $data['colors'] ?? [],
                );

                Notification::make()
                    ->title(__('filament.variants.generated_title'))
                    ->body(__('filament.variants.generated_body', $result))
                    ->success()
                    ->send();
            });
    }

    /**
     * The same friendly duplicate check is attached to whichever field is
     * visible for the product's enabled dimensions, because Filament skips
     * validation rules for hidden fields.
     */
    protected function duplicateCombinationRule(Get $get, ?ProductVariant $record): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($get, $record): void {
            $product = $this->getOwnerRecord();

            $color = trim((string) $get('color'));
            $size = trim((string) $get('size'));

            if (ProductVariant::existsFor(
                $product,
                $color === '' ? null : $color,
                $size === '' ? null : $size,
                $record?->getKey(),
            )) {
                $fail($this->duplicateCombinationMessage());
            }
        };
    }

    protected function duplicateCombinationMessage(): string
    {
        return __('filament.variants.duplicate');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('color')
                    ->label(__('filament.fields.color'))
                    ->required()
                    ->searchable()
                    ->options(fn (?ProductVariant $record) => self::colorOptions($record))
                    ->rule(
                        function (Get $get, ?ProductVariant $record): \Closure {
                            return $this->duplicateCombinationRule($get, $record);
                        },
                        true,
                    ),
                Select::make('size')
                    ->label(__('filament.fields.size'))
                    ->nullable()
                    ->searchable()
                    ->options(fn (?ProductVariant $record) => self::sizeOptions($record))
                    ->rule(fn (Get $get, ?ProductVariant $record): \Closure => $this->duplicateCombinationRule($get, $record)),
                TextInput::make('available_quantity')
                    ->label(__('filament.fields.available_quantity'))
                    ->default(50)
                    ->required()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(ProductVariant::MAX_QUANTITY),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('color')
            ->columns([
                TextColumn::make('color')
                    ->label(__('filament.fields.color'))
                    ->placeholder(__('filament.common.none'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('size')
                    ->label(__('filament.fields.size'))
                    ->placeholder(__('filament.common.none'))
                    ->searchable()
                    ->sortable(),
                TextInputColumn::make('available_quantity')
                    ->label(__('filament.fields.quantity'))
                    ->type('number')
                    ->rules(['required', 'integer', 'min:0', 'max:'.ProductVariant::MAX_QUANTITY])
                    ->sortable(),
            ])
            ->headerActions([
                $this->generateVariantsAction(),
                $this->excelExportAction(
                    'exportExcel',
                    __('filament.common.export_excel'),
                    fn (Builder $query) => new ProductVariantsExport($query),
                ),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (ProductVariant $record, DeleteAction $action): void {
                        if (! $record->isReferencedByActiveOrder()) {
                            return;
                        }

                        Notification::make()
                            ->title(__('filament.variants.delete_blocked_title'))
                            ->body(__('filament.variants.delete_blocked_body'))
                            ->danger()
                            ->send();

                        $action->cancel();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (EloquentCollection $records, DeleteBulkAction $action): void {
                            if (! $records->contains(fn (ProductVariant $record): bool => $record->isReferencedByActiveOrder())) {
                                return;
                            }

                            Notification::make()
                                ->title(__('filament.variants.bulk_delete_blocked_title'))
                                ->body(__('filament.variants.bulk_delete_blocked_body'))
                                ->danger()
                                ->send();

                            $action->cancel();
                        }),
                ]),
            ]);
    }
}

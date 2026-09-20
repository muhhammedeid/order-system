<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Filament\Concerns\HasExcelExport;
use App\Models\ProductVariant;
use App\Models\VariantColor;
use App\Models\VariantSize;
use App\Support\Exports\ProductVariantsExport;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
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

class VariantsRelationManager extends RelationManager
{
    use HasExcelExport;

    protected static string $relationship = 'variants';

    protected static ?string $title = 'Variants';

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
            ->label('توليد أصناف الألوان')
            ->icon(Heroicon::OutlinedSparkles)
            ->modalHeading('توليد أصناف الألوان')
            ->modalDescription('يتم إنشاء الأصناف الناقصة فقط؛ الأصناف الموجودة لا تتغير كمياتها.')
            ->modalSubmitActionLabel('توليد')
            ->form([
                Repeater::make('colors')
                    ->label(fn (): string => $this->getOwnerRecord()->color_enabled ? 'الألوان والكميات' : 'الكمية الافتراضية')
                    ->addActionLabel('إضافة لون')
                    ->addable(fn (): bool => $this->getOwnerRecord()->color_enabled)
                    ->defaultItems(1)
                    ->minItems(1)
                    ->columns(2)
                    ->schema([
                        Select::make('color')
                            ->label('اللون')
                            ->searchable()
                            ->required(fn (): bool => $this->getOwnerRecord()->color_enabled)
                            ->visible(fn (): bool => $this->getOwnerRecord()->color_enabled)
                            ->options(fn (): array => self::colorOptions(null)),
                        TextInput::make('quantity')
                            ->label('الكمية الافتراضية')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(ProductVariant::MAX_QUANTITY)
                            ->required(),
                    ]),
                CheckboxList::make('sizes')
                    ->label('المقاسات')
                    ->options(fn (): array => self::sizeOptions(null))
                    ->default(fn (): array => VariantSize::activeNames())
                    ->visible(fn (): bool => $this->getOwnerRecord()->size_enabled)
                    ->required(fn (): bool => $this->getOwnerRecord()->size_enabled),
            ])
            ->action(function (array $data): void {
                $result = ProductVariant::generateMissing(
                    $this->getOwnerRecord(),
                    $data['colors'] ?? [],
                    $data['sizes'] ?? null,
                );

                Notification::make()
                    ->title('توليد أصناف الألوان')
                    ->body("تم إنشاء {$result['created']} صنفًا، وتجاهل {$result['skipped']} صنفًا موجودًا.")
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

            $color = $product->color_enabled ? trim((string) $get('color')) : '';
            $size = $product->size_enabled ? trim((string) $get('size')) : '';

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
        $product = $this->getOwnerRecord();

        return match (true) {
            $product->color_enabled && $product->size_enabled => 'هذا اللون والمقاس مضافان بالفعل لهذا المنتج.',
            $product->color_enabled => 'هذا اللون مضاف بالفعل لهذا المنتج.',
            $product->size_enabled => 'هذا المقاس مضاف بالفعل لهذا المنتج.',
            default => 'هذا الصنف مضاف بالفعل لهذا المنتج.',
        };
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('color')
                    ->required(fn (): bool => $this->getOwnerRecord()->color_enabled)
                    ->visible(fn (): bool => $this->getOwnerRecord()->color_enabled)
                    ->searchable()
                    ->options(fn (?ProductVariant $record) => self::colorOptions($record))
                    ->rule(
                        function (Get $get, ?ProductVariant $record): \Closure {
                            return $this->duplicateCombinationRule($get, $record);
                        },
                        fn (): bool => $this->getOwnerRecord()->color_enabled,
                    ),
                Select::make('size')
                    ->required(fn () => $this->getOwnerRecord()->size_enabled)
                    ->visible(fn () => $this->getOwnerRecord()->size_enabled)
                    ->searchable()
                    ->options(fn (?ProductVariant $record) => self::sizeOptions($record))
                    ->rule(
                        function (Get $get, ?ProductVariant $record): \Closure {
                            return $this->duplicateCombinationRule($get, $record);
                        },
                        fn (): bool => ! $this->getOwnerRecord()->color_enabled && $this->getOwnerRecord()->size_enabled,
                    ),
                TextInput::make('available_quantity')
                    ->label('Available Quantity')
                    ->required()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(ProductVariant::MAX_QUANTITY)
                    ->rule(
                        function (Get $get, ?ProductVariant $record): \Closure {
                            return $this->duplicateCombinationRule($get, $record);
                        },
                        fn (): bool => ! $this->getOwnerRecord()->color_enabled && ! $this->getOwnerRecord()->size_enabled,
                    ),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('color')
            ->columns([
                TextColumn::make('color')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('size')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                TextInputColumn::make('available_quantity')
                    ->label('Quantity')
                    ->type('number')
                    ->rules(['required', 'integer', 'min:0', 'max:'.ProductVariant::MAX_QUANTITY])
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
                $this->generateVariantsAction(),
                $this->excelExportAction(
                    'exportExcel',
                    'تصدير Excel',
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
                            ->title('تعذّر حذف المقاس')
                            ->body('لا يمكن حذف هذا المقاس لأنه مرتبط بطلب نشط. يمكنك إبقاء المقاس كما هو أو تعديل الكمية المتاحة بدلًا من حذفه.')
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
                                ->title('تعذّر حذف الأصناف المحددة')
                                ->body('لا يمكن حذف بعض الأصناف المحددة لأنها مرتبطة بطلبات نشطة. لم يتم حذف أي صنف؛ يمكنك تعديل الكميات المتاحة بدلًا من الحذف.')
                                ->danger()
                                ->send();

                            $action->cancel();
                        }),
                ]),
            ]);
    }
}

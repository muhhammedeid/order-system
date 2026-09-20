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
            ->label('إضافة لون بالمقاسات الافتراضية')
            ->icon(Heroicon::OutlinedSparkles)
            ->modalHeading('إضافة الألوان بالمقاسات الخمسة')
            ->modalDescription('كل لون سيُضاف تلقائيًا بجميع المقاسات الخمسة النشطة. الأصناف الموجودة لا تتغير.')
            ->modalSubmitActionLabel('إضافة')
            ->form([
                Repeater::make('colors')
                    ->label('الألوان والكميات')
                    ->addActionLabel('إضافة لون')
                    ->defaultItems(1)
                    ->minItems(1)
                    ->columns(2)
                    ->schema([
                        Select::make('color')
                            ->label('اللون')
                            ->searchable()
                            ->required()
                            ->options(fn (): array => self::colorOptions(null)),
                        TextInput::make('quantity')
                            ->label('الكمية الافتراضية')
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
                        ->title('تعذرت إضافة الألوان')
                        ->body('يجب أن يكون هناك 5 مقاسات نشطة بالضبط في إعدادات المقاسات.')
                        ->danger()
                        ->send();

                    return;
                }

                $result = ProductVariant::generateMissing(
                    $this->getOwnerRecord(),
                    $data['colors'] ?? [],
                );

                Notification::make()
                    ->title('تمت إضافة الألوان')
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
        return 'هذا اللون والمقاس مضافان بالفعل لهذا المنتج.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('color')
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
                    ->nullable()
                    ->searchable()
                    ->options(fn (?ProductVariant $record) => self::sizeOptions($record))
                    ->rule(fn (Get $get, ?ProductVariant $record): \Closure => $this->duplicateCombinationRule($get, $record)),
                TextInput::make('available_quantity')
                    ->label('Available Quantity')
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

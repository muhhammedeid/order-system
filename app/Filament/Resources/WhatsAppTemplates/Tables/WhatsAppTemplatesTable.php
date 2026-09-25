<?php

namespace App\Filament\Resources\WhatsAppTemplates\Tables;

use App\Enums\WhatsAppTemplateType;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\WhatsAppTemplate;
use App\Support\WhatsApp\Templates\WhatsAppTemplateException;
use App\Support\WhatsApp\Templates\WhatsAppTemplateRenderer;
use App\Support\WhatsApp\Templates\WhatsAppTemplateVariables;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WhatsAppTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.whatsapp.templates.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('key')
                    ->label(__('admin.whatsapp.templates.fields.key'))
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('type')
                    ->label(__('admin.whatsapp.templates.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn (?WhatsAppTemplateType $state): string => $state?->label() ?? '')
                    ->color(fn (?WhatsAppTemplateType $state): string => match ($state) {
                        WhatsAppTemplateType::Marketing => 'info',
                        WhatsAppTemplateType::Order => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('body')
                    ->label(__('admin.whatsapp.templates.fields.body'))
                    ->limit(80)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('active')
                    ->label(__('admin.whatsapp.templates.fields.active'))
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label(__('admin.whatsapp.templates.fields.updated_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                self::previewAction(),
                DeleteAction::make()
                    ->visible(fn (WhatsAppTemplate $record): bool => $record->key === null),
            ])
            ->defaultSort('name');
    }

    /**
     * Read-only, escaped, non-sending preview against real Customer/Product
     * records. Product becomes required when the template uses product
     * variables; missing context is reported instead of rendering empty text.
     */
    private static function previewAction(): Action
    {
        return Action::make('preview')
            ->label(__('admin.whatsapp.templates.actions.preview'))
            ->icon('heroicon-o-eye')
            ->modalHeading(fn (WhatsAppTemplate $record): string => __('admin.whatsapp.templates.preview.heading', [
                'name' => $record->name,
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('admin.whatsapp.templates.actions.close'))
            ->form([
                Select::make('order_id')
                    ->label(__('admin.whatsapp.templates.preview.order'))
                    ->searchable()
                    ->live()
                    ->visible(fn (WhatsAppTemplate $record): bool => WhatsAppTemplateVariables::usesOrderContext($record->body))
                    ->required(fn (WhatsAppTemplate $record): bool => WhatsAppTemplateVariables::usesOrderContext($record->body))
                    ->getSearchResultsUsing(fn (string $search): array => Order::query()
                        ->where('order_number', 'like', "%{$search}%")
                        ->orderByDesc('id')
                        ->limit(20)
                        ->pluck('order_number', 'id')
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => Order::query()->whereKey($value)->value('order_number')),
                Select::make('customer_id')
                    ->label(__('admin.whatsapp.templates.preview.customer'))
                    ->searchable()
                    ->live()
                    ->visible(fn (WhatsAppTemplate $record): bool => ! WhatsAppTemplateVariables::usesOrderContext($record->body))
                    ->required(fn (WhatsAppTemplate $record): bool => ! WhatsAppTemplateVariables::usesOrderContext($record->body))
                    ->getSearchResultsUsing(fn (string $search): array => Customer::query()
                        ->where(function (Builder $query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%")
                                ->orWhere('customer_code', 'like', "%{$search}%");
                        })
                        ->orderBy('name')
                        ->limit(20)
                        ->pluck('name', 'id')
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => Customer::query()->whereKey($value)->value('name')),
                Select::make('product_id')
                    ->label(__('admin.whatsapp.templates.preview.product'))
                    ->searchable()
                    ->live()
                    ->required(fn (WhatsAppTemplate $record): bool => WhatsAppTemplateVariables::usesProductContext($record->body))
                    ->helperText(fn (WhatsAppTemplate $record): ?string => WhatsAppTemplateVariables::usesProductContext($record->body)
                        ? __('admin.whatsapp.templates.preview.product_required')
                        : null)
                    ->getSearchResultsUsing(fn (string $search): array => Product::query()
                        ->where(function (Builder $query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('product_code', 'like', "%{$search}%");
                        })
                        ->orderBy('name')
                        ->limit(20)
                        ->pluck('name', 'id')
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => Product::query()->whereKey($value)->value('name')),
                Placeholder::make('preview')
                    ->hiddenLabel()
                    ->extraAttributes(['class' => 'rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/40'])
                    ->listWithLineBreaks()
                    ->bulleted(false)
                    ->helperText(__('admin.whatsapp.templates.preview.hint'))
                    ->content(function (Get $get, WhatsAppTemplate $record): array {
                        if (WhatsAppTemplateVariables::usesOrderContext($record->body)) {
                            $order = filled($get('order_id'))
                                ? Order::query()->with(['customer', 'items'])->find($get('order_id'))
                                : null;

                            if ($order === null) {
                                return [__('admin.whatsapp.templates.preview.choose_order')];
                            }

                            try {
                                $rendered = app(WhatsAppTemplateRenderer::class)->renderForOrder($record, $order);
                            } catch (WhatsAppTemplateException $exception) {
                                return [$exception->getMessage()];
                            }

                            return explode("\n", $rendered);
                        }

                        $customer = Customer::query()->find($get('customer_id'));

                        if ($customer === null) {
                            return [__('admin.whatsapp.templates.preview.choose_customer')];
                        }

                        $product = filled($get('product_id'))
                            ? Product::query()->find($get('product_id'))
                            : null;

                        try {
                            $rendered = app(WhatsAppTemplateRenderer::class)->render($record, $customer, $product);
                        } catch (WhatsAppTemplateException $exception) {
                            return [$exception->getMessage()];
                        }

                        return explode("\n", $rendered);
                    }),
            ]);
    }
}

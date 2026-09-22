<?php

namespace App\Filament\Resources\WhatsAppConversations\Pages;

use App\Contracts\WhatsAppGateway;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Filament\Resources\WhatsAppConversations\WhatsAppConversationResource;
use App\Models\Customer;
use App\Support\WhatsApp\Inbox\ProviderMessageId;
use App\Support\WhatsApp\WhatsAppException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;

/**
 * Conversation detail: identity, chronological history, outbound delivery
 * state and a text reply composer. Message bodies are rendered escaped.
 */
class ViewWhatsAppConversation extends ViewRecord
{
    protected static string $resource = WhatsAppConversationResource::class;

    protected string $view = 'filament.resources.whatsapp-conversations.view';

    public string $replyBody = '';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->markRead();
    }

    #[Computed]
    public function history(): Collection
    {
        return $this->record->messages()
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();
    }

    public function sendReply(): void
    {
        $validated = $this->validate([
            'replyBody' => ['required', 'string', 'max:4096'],
        ], [], [
            'replyBody' => __('admin.whatsapp.conversation.composer'),
        ]);

        $gateway = app(WhatsAppGateway::class);

        if (! $gateway->enabled()) {
            Notification::make()
                ->title(__('admin.whatsapp.notifications.disabled'))
                ->warning()
                ->send();

            return;
        }

        $body = trim((string) $validated['replyBody']);

        $message = $this->record->messages()->create([
            'direction' => WhatsAppMessageDirection::Outbound,
            'message_type' => WhatsAppMessageType::Text,
            'body' => $body,
            'status' => WhatsAppMessageStatus::Pending,
            'occurred_at' => now(),
        ]);

        try {
            $sent = $gateway->sendText($this->record->provider_chat_id, $body);
        } catch (WhatsAppException $exception) {
            $message->forceFill(['status' => WhatsAppMessageStatus::Failed])->save();

            Log::warning('WhatsApp reply failed', [
                'conversation_id' => $this->record->id,
                'reason' => $exception->getMessage(),
            ]);

            unset($this->history);

            Notification::make()
                ->title(__('admin.whatsapp.notifications.action_failed'))
                ->body(__('admin.whatsapp.conversation.send_failed_body'))
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $message->forceFill([
            'provider_message_id' => ProviderMessageId::normalize($sent->providerId),
            'status' => WhatsAppMessageStatus::Sent,
        ])->save();

        $this->record->forceFill([
            'last_message_at' => $message->occurred_at,
            'last_message_preview' => $message->previewText(),
            'last_message_direction' => WhatsAppMessageDirection::Outbound->value,
        ])->save();

        $this->replyBody = '';
        unset($this->history);

        Notification::make()
            ->title(__('admin.whatsapp.conversation.sent'))
            ->success()
            ->send();
    }

    public function linkCustomer(int $customerId): void
    {
        $customer = Customer::query()->find($customerId);

        if ($customer === null) {
            Notification::make()
                ->title(__('admin.whatsapp.conversation.customer_not_found'))
                ->danger()
                ->send();

            return;
        }

        $this->record->forceFill(['customer_id' => $customer->id])->save();

        Notification::make()
            ->title(__('admin.whatsapp.conversation.customer_linked'))
            ->success()
            ->send();
    }

    public function unlinkCustomer(): void
    {
        $this->record->forceFill(['customer_id' => null])->save();

        Notification::make()
            ->title(__('admin.whatsapp.conversation.customer_unlinked'))
            ->success()
            ->send();
    }

    public function refreshConversation(): void
    {
        $this->record->refresh();
        unset($this->history);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshConversation')
                ->label(__('admin.whatsapp.conversation.refresh'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->refreshConversation()),

            Action::make('linkCustomer')
                ->label(fn (): string => $this->record->customer_id !== null
                    ? __('admin.whatsapp.conversation.change_customer')
                    : __('admin.whatsapp.conversation.link_customer'))
                ->icon('heroicon-o-user-plus')
                ->visible(fn (): bool => $this->record->customer_id === null)
                ->form([
                    Select::make('customer_id')
                        ->label(__('admin.whatsapp.conversation.customer'))
                        ->searchable()
                        ->required()
                        ->getSearchResultsUsing(fn (string $search): array => Customer::query()
                            ->where(function ($query) use ($search): void {
                                $query->where('name', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%")
                                    ->orWhere('customer_code', 'like', "%{$search}%");
                            })
                            ->orderBy('name')
                            ->limit(20)
                            ->pluck('name', 'id')
                            ->all())
                        ->getOptionLabelUsing(fn ($value): ?string => Customer::query()->whereKey($value)->value('name')),
                ])
                ->action(fn (array $data) => $this->linkCustomer((int) $data['customer_id'])),

            Action::make('changeCustomer')
                ->label(__('admin.whatsapp.conversation.change_customer'))
                ->icon('heroicon-o-user-plus')
                ->visible(fn (): bool => $this->record->customer_id !== null)
                ->form([
                    Select::make('customer_id')
                        ->label(__('admin.whatsapp.conversation.customer'))
                        ->searchable()
                        ->required()
                        ->default(fn (): ?int => $this->record->customer_id)
                        ->getSearchResultsUsing(fn (string $search): array => Customer::query()
                            ->where(function ($query) use ($search): void {
                                $query->where('name', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%")
                                    ->orWhere('customer_code', 'like', "%{$search}%");
                            })
                            ->orderBy('name')
                            ->limit(20)
                            ->pluck('name', 'id')
                            ->all())
                        ->getOptionLabelUsing(fn ($value): ?string => Customer::query()->whereKey($value)->value('name')),
                ])
                ->action(fn (array $data) => $this->linkCustomer((int) $data['customer_id'])),

            Action::make('unlinkCustomer')
                ->label(__('admin.whatsapp.conversation.unlink_customer'))
                ->icon('heroicon-o-user-minus')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('admin.whatsapp.conversation.unlink_heading'))
                ->modalDescription(__('admin.whatsapp.conversation.unlink_description'))
                ->visible(fn (): bool => $this->record->customer_id !== null)
                ->action(fn () => $this->unlinkCustomer()),
        ];
    }
}

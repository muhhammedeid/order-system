<?php

namespace App\Livewire;

use App\Contracts\WhatsAppGateway;
use App\Models\Order;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Support\WhatsApp\Order\OrderMessageTemplates;
use App\Support\WhatsApp\Outbound\MessageSender;
use App\Support\WhatsApp\Outbound\OrderConversations;
use App\Support\WhatsApp\WhatsAppException;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Compact WhatsApp communication panel embedded in the order view pages.
 *
 * Sending is always explicit: no conversation shell is created and no
 * provider call is made by rendering the panel. First contact verifies the
 * customer number with the provider and distinguishes an invalid number from
 * an unavailable WhatsApp service.
 */
class OrderWhatsAppPanel extends Component
{
    public int $orderId = 0;

    public string $messageBody = '';

    public ?string $previewKey = null;

    public ?string $previewBody = null;

    public ?int $selectedConversationId = null;

    public function mount(int $order): void
    {
        $this->orderId = $order;
    }

    #[Computed]
    public function orderRecord(): Order
    {
        return Order::query()
            ->with([
                'customer:id,name,whatsapp,phone',
                'items:id,order_id,delivered_quantity',
            ])
            ->findOrFail($this->orderId);
    }

    #[Computed]
    public function enabled(): bool
    {
        return app(WhatsAppGateway::class)->enabled();
    }

    /**
     * @return Collection<int, WhatsAppConversation>
     */
    #[Computed]
    public function conversations(): Collection
    {
        return app(OrderConversations::class)->for($this->orderRecord);
    }

    #[Computed]
    public function candidateNumber(): ?string
    {
        return app(OrderConversations::class)->candidateNumber($this->orderRecord);
    }

    #[Computed]
    public function targetConversation(): ?WhatsAppConversation
    {
        $conversations = $this->conversations;

        if ($conversations->count() === 1) {
            return $conversations->first();
        }

        if ($this->selectedConversationId !== null) {
            return $conversations->firstWhere('id', $this->selectedConversationId);
        }

        return null;
    }

    /**
     * Only messages explicitly linked to this order.
     *
     * @return Collection<int, WhatsAppMessage>
     */
    #[Computed]
    public function recentMessages(): Collection
    {
        return $this->orderRecord->whatsappMessages()
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();
    }

    /**
     * @return array<int, array{key: string, label: string, body: string}>
     */
    #[Computed]
    public function templates(): array
    {
        return OrderMessageTemplates::for($this->orderRecord);
    }

    public function sendCustomMessage(): void
    {
        $validated = $this->validate([
            'messageBody' => ['required', 'string', 'max:4096'],
        ], [], [
            'messageBody' => __('admin.whatsapp.order.custom_placeholder'),
        ]);

        $this->dispatchSend(trim((string) $validated['messageBody']));
    }

    public function previewTemplate(string $key): void
    {
        $order = $this->orderRecord;

        if (! OrderMessageTemplates::isAvailable($key, $order)) {
            Notification::make()
                ->title(__('admin.whatsapp.order.template_unavailable'))
                ->warning()
                ->send();

            return;
        }

        $templates = collect(OrderMessageTemplates::for($order))->keyBy('key');

        $this->previewKey = $key;
        $this->previewBody = $templates[$key]['body'] ?? null;
    }

    public function cancelTemplatePreview(): void
    {
        $this->previewKey = null;
        $this->previewBody = null;
    }

    public function sendTemplate(): void
    {
        if ($this->previewKey === null || $this->previewBody === null) {
            return;
        }

        $this->dispatchSend($this->previewBody);
    }

    private function dispatchSend(string $body): void
    {
        $gateway = app(WhatsAppGateway::class);
        $order = $this->orderRecord;

        if (! $gateway->enabled()) {
            Notification::make()
                ->title(__('admin.whatsapp.notifications.disabled'))
                ->warning()
                ->send();

            return;
        }

        $conversation = $this->resolveTargetConversation($order);

        if ($conversation === null) {
            return;
        }

        try {
            app(MessageSender::class)->send($conversation, $body, $order->id);
        } catch (WhatsAppException $exception) {
            Log::warning('WhatsApp order message failed', [
                'order_id' => $order->id,
                'conversation_id' => $conversation->id,
                'reason' => $exception->getMessage(),
            ]);

            Notification::make()
                ->title(__('admin.whatsapp.notifications.action_failed'))
                ->body(__('admin.whatsapp.order.send_failed_body'))
                ->danger()
                ->persistent()
                ->send();

            $this->refreshPanel();

            return;
        }

        $this->messageBody = '';
        $this->cancelTemplatePreview();
        $this->refreshPanel();

        Notification::make()
            ->title(__('admin.whatsapp.order.sent'))
            ->success()
            ->send();
    }

    /**
     * Single conversation -> use it. Several -> require an explicit choice.
     * None -> verify the customer number (first contact) and create the shell
     * only now, on an actual send attempt.
     */
    private function resolveTargetConversation(Order $order): ?WhatsAppConversation
    {
        $conversations = $this->conversations;

        if ($conversations->count() === 1) {
            return $conversations->first();
        }

        if ($conversations->count() > 1) {
            if ($this->selectedConversationId === null
                || ! $conversations->contains('id', $this->selectedConversationId)) {
                Notification::make()
                    ->title(__('admin.whatsapp.order.select_conversation'))
                    ->warning()
                    ->persistent()
                    ->send();

                return null;
            }

            return $conversations->firstWhere('id', $this->selectedConversationId);
        }

        if ($this->candidateNumber === null) {
            Notification::make()
                ->title(__('admin.whatsapp.order.no_number'))
                ->warning()
                ->persistent()
                ->send();

            return null;
        }

        try {
            $conversation = app(OrderConversations::class)->firstContact($order);
        } catch (WhatsAppException $exception) {
            Log::warning('WhatsApp number verification failed', [
                'order_id' => $order->id,
                'reason' => $exception->getMessage(),
            ]);

            Notification::make()
                ->title(__('admin.whatsapp.order.service_unavailable'))
                ->warning()
                ->persistent()
                ->send();

            return null;
        }

        if ($conversation === null) {
            Notification::make()
                ->title(__('admin.whatsapp.order.number_invalid'))
                ->warning()
                ->persistent()
                ->send();

            return null;
        }

        $this->selectedConversationId = $conversation->id;

        return $conversation;
    }

    private function refreshPanel(): void
    {
        unset($this->conversations, $this->targetConversation, $this->recentMessages);
    }

    public function render()
    {
        return view('livewire.order-whatsapp-panel');
    }
}

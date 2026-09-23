@php
    use App\Enums\WhatsAppMessageStatus;

    $conversation = $this->record;
    $integrationEnabled = $this->whatsappEnabled();
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::card>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 space-y-1">
                    <h2 class="text-lg font-semibold">{{ $conversation->displayTitle() }}</h2>

                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('admin.whatsapp.conversation.linked_customer') }}:
                        @if ($conversation->customer)
                            <span class="font-semibold text-gray-950 dark:text-white">{{ $conversation->customer->name }}</span>
                        @else
                            <span>{{ __('admin.whatsapp.conversation.not_linked') }}</span>
                        @endif

                        @if (filled($conversation->resolved_phone))
                            · <span dir="ltr" class="font-mono">+{{ $conversation->resolved_phone }}</span>
                        @endif
                    </p>

                    <p class="text-xs text-gray-400 dark:text-gray-500">
                        {{ __('admin.whatsapp.conversation.provider_chat') }}:
                        <span dir="ltr" class="font-mono">{{ $conversation->provider_chat_id }}</span>
                    </p>
                </div>

                <x-filament::badge :color="$conversation->customer_id !== null ? 'success' : 'gray'">
                    {{ $conversation->customer_id !== null ? __('admin.whatsapp.inbox.linked_yes') : __('admin.whatsapp.inbox.linked_no') }}
                </x-filament::badge>
            </div>
        </x-filament::card>

        <x-filament::card>
            <div
                class="max-h-[65vh] space-y-3 overflow-y-auto pe-2"
                x-data
                x-init="$nextTick(() => { $el.scrollTop = $el.scrollHeight })"
                wire:key="whatsapp-timeline-{{ $this->history->count() }}"
            >
                @forelse ($this->history as $storedMessage)
                    @php
                        $outbound = $storedMessage->isOutbound();
                        $failed = $outbound && $storedMessage->status === WhatsAppMessageStatus::Failed;

                        $statusIcon = match ($storedMessage->status) {
                            WhatsAppMessageStatus::Pending => 'heroicon-o-clock',
                            WhatsAppMessageStatus::Sent => 'heroicon-o-check',
                            WhatsAppMessageStatus::Delivered => 'heroicon-o-check-circle',
                            WhatsAppMessageStatus::Read => 'heroicon-o-check-badge',
                            WhatsAppMessageStatus::Failed => 'heroicon-o-exclamation-triangle',
                            default => null,
                        };
                    @endphp

                    <div class="flex">
                        <div @class([
                            'max-w-[85%] rounded-2xl px-3 py-2 text-sm shadow-sm sm:max-w-[75%]',
                            'ms-auto bg-primary-600 text-white' => $outbound && ! $failed,
                            'ms-auto border border-danger-300 bg-danger-50 text-danger-900 dark:border-danger-700 dark:bg-danger-950/40 dark:text-danger-200' => $failed,
                            'me-auto bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-gray-100' => ! $outbound,
                        ])>
                            <div class="whitespace-pre-wrap break-words">
                                {{ $storedMessage->body ?? $storedMessage->message_type->label() }}
                            </div>

                            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] opacity-80">
                                <time dir="ltr">{{ $storedMessage->occurred_at?->format('Y-m-d H:i') }}</time>

                                @if ($outbound && $storedMessage->status)
                                    <span class="inline-flex items-center gap-0.5">
                                        @if ($statusIcon)
                                            <x-filament::icon :icon="$statusIcon" class="h-3.5 w-3.5" />
                                        @endif
                                        {{ $storedMessage->status->label() }}
                                    </span>
                                @endif

                                @if ($storedMessage->order_id)
                                    <a
                                        href="{{ route('filament.admin.resources.order-management.view', ['record' => $storedMessage->order_id]) }}"
                                        title="{{ __('admin.whatsapp.order.badge_title') }}"
                                        class="inline-flex"
                                    >
                                        <x-filament::badge color="info">
                                            #{{ $storedMessage->order?->order_number }}
                                        </x-filament::badge>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ __('admin.whatsapp.conversation.empty') }}
                    </p>
                @endforelse
            </div>
        </x-filament::card>

        <x-filament::card>
            @unless ($integrationEnabled)
                <div class="mb-3">
                    <x-filament::callout
                        color="warning"
                        :description="__('admin.whatsapp.notifications.disabled')"
                    />
                </div>
            @endunless

            <form wire:submit="sendReply" class="space-y-3">
                <x-filament::input.wrapper class="fi-fo-textarea" :disabled="! $integrationEnabled">
                    <textarea
                        wire:model="replyBody"
                        rows="3"
                        maxlength="4096"
                        @disabled(! $integrationEnabled)
                        placeholder="{{ __('admin.whatsapp.conversation.composer_placeholder') }}"
                    ></textarea>
                </x-filament::input.wrapper>

                @error('replyBody')
                    <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                @enderror

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-gray-400 dark:text-gray-500">
                        {{ __('admin.whatsapp.conversation.composer_hint') }}
                    </p>

                    <x-filament::button
                        type="submit"
                        :disabled="! $integrationEnabled"
                        wire:loading.attr="disabled"
                        wire:target="sendReply"
                    >
                        {{ __('admin.whatsapp.conversation.send') }}
                    </x-filament::button>
                </div>
            </form>
        </x-filament::card>
    </div>
</x-filament-panels::page>

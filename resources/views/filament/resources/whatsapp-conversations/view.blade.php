@php
    $conversation = $this->record;
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::card>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="space-y-1">
                    <h2 class="text-base font-semibold">{{ $conversation->displayTitle() }}</h2>

                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('admin.whatsapp.conversation.linked_customer') }}:
                        @if ($conversation->customer)
                            <span class="font-semibold">{{ $conversation->customer->name }}</span>
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
            <div class="max-h-[28rem] space-y-3 overflow-y-auto pe-2">
                @forelse ($this->history as $storedMessage)
                    @php($outbound = $storedMessage->isOutbound())

                    <div @class(['flex' => true, 'justify-end' => $outbound, 'justify-start' => ! $outbound])>
                        <div @class([
                            'max-w-[80%] rounded-xl px-3 py-2 text-sm shadow-sm',
                            'bg-primary-600 text-white' => $outbound,
                            'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-gray-100' => ! $outbound,
                        ])>
                            <div class="whitespace-pre-wrap break-words">
                                {{ $storedMessage->body ?? $storedMessage->message_type->label() }}
                            </div>

                            <div class="mt-1 flex items-center gap-2 text-[11px] opacity-75">
                                <span dir="ltr">{{ $storedMessage->occurred_at?->format('Y-m-d H:i') }}</span>

                                @if ($outbound && $storedMessage->status)
                                    <span>· {{ $storedMessage->status->label() }}</span>
                                @endif

                                @if ($storedMessage->order_id)
                                    <a
                                        href="{{ route('filament.admin.resources.order-management.view', ['record' => $storedMessage->order_id]) }}"
                                        title="{{ __('admin.whatsapp.order.badge_title') }}"
                                        class="underline"
                                    >
                                        #{{ $storedMessage->order?->order_number }}
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
            <form wire:submit="sendReply" class="space-y-3">
                <textarea
                    wire:model="replyBody"
                    rows="3"
                    maxlength="4096"
                    placeholder="{{ __('admin.whatsapp.conversation.composer_placeholder') }}"
                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                ></textarea>

                @error('replyBody')
                    <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                @enderror

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-gray-400 dark:text-gray-500">
                        {{ __('admin.whatsapp.conversation.composer_hint') }}
                    </p>

                    <x-filament::button
                        type="submit"
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
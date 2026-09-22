@php
    $order = $this->orderRecord;
    $conversations = $this->conversations;
    $target = $this->targetConversation;
    $conversationUrl = $target
        ? route('filament.admin.resources.whatsapp-conversations.view', ['record' => $target])
        : null;
@endphp

<x-filament::card>
    <div class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
                <h2 class="text-base font-semibold">
                    {{ __('admin.whatsapp.order.section_title') }}
                </h2>

                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('admin.whatsapp.order.customer') }}:
                    <span class="font-semibold">{{ $order->customer?->name }}</span>

                    @if (filled($this->candidateNumber))
                        · <span dir="ltr" class="font-mono">{{ $this->candidateNumber }}</span>
                    @endif
                </p>

                @if ($target)
                    <p class="text-xs text-gray-400 dark:text-gray-500">
                        {{ __('admin.whatsapp.order.conversation') }}:
                        <span dir="ltr" class="font-mono">{{ $target->provider_chat_id }}</span>

                        @if (filled($target->resolved_phone))
                            · <span dir="ltr" class="font-mono">+{{ $target->resolved_phone }}</span>
                        @endif
                    </p>
                @endif
            </div>

            <x-filament::badge :color="$this->enabled ? 'success' : 'gray'">
                {{ $this->enabled ? __('admin.whatsapp.order.service_available') : __('admin.whatsapp.order.service_disabled') }}
            </x-filament::badge>
        </div>

        @if ($conversationUrl)
            <div>
                <x-filament::button
                    tag="a"
                    :href="$conversationUrl"
                    size="sm"
                    color="gray"
                    icon="heroicon-o-arrow-top-right-on-square"
                >
                    {{ __('admin.whatsapp.order.open_conversation') }}
                </x-filament::button>
            </div>
        @endif

        @if ($conversations->count() > 1)
            <div class="space-y-1">
                <label class="text-sm font-medium" for="order-whatsapp-conversation">
                    {{ __('admin.whatsapp.order.target_conversation') }}
                </label>

                <select
                    id="order-whatsapp-conversation"
                    wire:model="selectedConversationId"
                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                >
                    <option value="">{{ __('admin.whatsapp.order.choose_conversation') }}</option>

                    @foreach ($conversations as $conversation)
                        <option value="{{ $conversation->id }}">
                            {{ $conversation->provider_chat_id }}@if (filled($conversation->resolved_phone)) · +{{ $conversation->resolved_phone }}@endif @if ($conversation->last_message_at) · {{ $conversation->last_message_at->format('Y-m-d H:i') }}@endif
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        <div>
            <h3 class="text-sm font-semibold">{{ __('admin.whatsapp.order.recent') }}</h3>

            <ul class="mt-2 space-y-1 text-sm">
                @forelse ($this->recentMessages as $storedMessage)
                    <li class="flex flex-wrap items-center gap-2">
                        <span dir="ltr" class="text-xs text-gray-400 dark:text-gray-500">
                            {{ $storedMessage->occurred_at?->format('Y-m-d H:i') }}
                        </span>

                        <span>{{ $storedMessage->isInbound() ? __('admin.whatsapp.directions.inbound') : __('admin.whatsapp.directions.outbound') }}</span>

                        <span class="whitespace-pre-wrap break-words">
                            {{ $storedMessage->body ?? $storedMessage->message_type->label() }}
                        </span>

                        @if ($storedMessage->isOutbound() && $storedMessage->status)
                            <span class="text-xs text-gray-400 dark:text-gray-500">· {{ $storedMessage->status->label() }}</span>
                        @endif
                    </li>
                @empty
                    <li class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('admin.whatsapp.order.no_recent') }}
                    </li>
                @endforelse
            </ul>

            @if ($conversationUrl)
                <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                    {{ __('admin.whatsapp.order.other_history') }}
                </p>
            @endif
        </div>

        @if ($this->enabled)
            <div class="space-y-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                <div class="space-y-1">
                    <textarea
                        wire:model="messageBody"
                        rows="3"
                        maxlength="4096"
                        placeholder="{{ __('admin.whatsapp.order.custom_placeholder') }}"
                        class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                    ></textarea>

                    @error('messageBody')
                        <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap gap-2">
                        @foreach ($this->templates as $template)
                            <x-filament::button
                                size="sm"
                                color="gray"
                                wire:click="previewTemplate('{{ $template['key'] }}')"
                            >
                                {{ $template['label'] }}
                            </x-filament::button>
                        @endforeach
                    </div>

                    <x-filament::button
                        size="sm"
                        wire:click="sendCustomMessage"
                        wire:loading.attr="disabled"
                        wire:target="sendCustomMessage"
                    >
                        {{ __('admin.whatsapp.order.send') }}
                    </x-filament::button>
                </div>

                @if ($previewBody !== null)
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                            {{ __('admin.whatsapp.order.preview_title') }}
                        </p>

                        <p class="mt-1 whitespace-pre-wrap break-words text-sm">{{ $previewBody }}</p>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <x-filament::button
                                size="sm"
                                wire:click="sendTemplate"
                                wire:loading.attr="disabled"
                                wire:target="sendTemplate"
                            >
                                {{ __('admin.whatsapp.order.confirm_send') }}
                            </x-filament::button>

                            <x-filament::button size="sm" color="gray" wire:click="cancelTemplatePreview">
                                {{ __('admin.whatsapp.order.cancel') }}
                            </x-filament::button>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-filament::card>
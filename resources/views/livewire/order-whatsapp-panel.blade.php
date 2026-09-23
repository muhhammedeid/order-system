@php
    use App\Enums\WhatsAppMessageStatus;

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
            <div class="min-w-0 space-y-1">
                <h2 class="text-base font-semibold">
                    {{ __('admin.whatsapp.order.section_title') }}
                </h2>

                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('admin.whatsapp.order.customer') }}:
                    <span class="font-semibold text-gray-950 dark:text-white">{{ $order->customer?->name }}</span>

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

                <x-filament::input.wrapper>
                    <x-filament::input.select
                        id="order-whatsapp-conversation"
                        wire:model="selectedConversationId"
                    >
                        <option value="">{{ __('admin.whatsapp.order.choose_conversation') }}</option>

                        @foreach ($conversations as $conversation)
                            <option value="{{ $conversation->id }}">
                                {{ $conversation->provider_chat_id }}@if (filled($conversation->resolved_phone)) · +{{ $conversation->resolved_phone }}@endif @if ($conversation->last_message_at) · {{ $conversation->last_message_at->format('Y-m-d H:i') }}@endif
                            </option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        @endif

        <div>
            <h3 class="text-sm font-semibold">{{ __('admin.whatsapp.order.recent') }}</h3>

            <ul class="mt-2 divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($this->recentMessages as $storedMessage)
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

                    <li class="flex items-start gap-2 py-2">
                        <x-filament::icon
                            :icon="$outbound ? 'heroicon-o-arrow-up-right' : 'heroicon-o-arrow-down-left'"
                            @class([
                                'mt-0.5 h-4 w-4 shrink-0',
                                'text-danger-500' => $failed,
                                'text-gray-400 dark:text-gray-500' => ! $failed,
                            ])
                        />

                        <div class="min-w-0 flex-1 space-y-0.5">
                            <p class="whitespace-pre-wrap break-words text-sm">
                                {{ $storedMessage->body ?? $storedMessage->message_type->label() }}
                            </p>

                            <p class="flex flex-wrap items-center gap-x-2 text-xs text-gray-400 dark:text-gray-500">
                                <span>{{ $outbound ? __('admin.whatsapp.directions.outbound') : __('admin.whatsapp.directions.inbound') }}</span>
                                <time dir="ltr">{{ $storedMessage->occurred_at?->format('Y-m-d H:i') }}</time>

                                @if ($outbound && $storedMessage->status)
                                    <span @class([
                                        'inline-flex items-center gap-0.5',
                                        'text-danger-600 dark:text-danger-400' => $failed,
                                    ])>
                                        @if ($statusIcon)
                                            <x-filament::icon :icon="$statusIcon" class="h-3.5 w-3.5" />
                                        @endif
                                        {{ $storedMessage->status->label() }}
                                    </span>
                                @endif
                            </p>
                        </div>
                    </li>
                @empty
                    <li class="py-2 text-sm text-gray-500 dark:text-gray-400">
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
                <x-filament::input.wrapper class="fi-fo-textarea">
                    <textarea
                        wire:model="messageBody"
                        rows="3"
                        maxlength="4096"
                        placeholder="{{ __('admin.whatsapp.order.custom_placeholder') }}"
                    ></textarea>
                </x-filament::input.wrapper>

                @error('messageBody')
                    <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                @enderror

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
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/40">
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

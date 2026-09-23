@php
    $view = $this->sessionView();
    $qrUrl = route('filament.admin.whatsapp.qr');

    $serviceColor = ! $enabled
        ? 'gray'
        : ($serviceHealthy ? 'success' : 'danger');

    $serviceLabel = ! $enabled
        ? __('admin.whatsapp.service.disabled')
        : ($serviceHealthy ? __('admin.whatsapp.service.healthy') : __('admin.whatsapp.service.unavailable'));
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Primary: current WhatsApp/session state, identity and guidance. --}}
            <x-filament::card class="lg:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-1">
                        <h2 class="text-base font-semibold">
                            {{ __('admin.whatsapp.account.title') }}
                        </h2>

                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('admin.whatsapp.account.session') }}:
                            <span dir="ltr" class="font-mono">{{ $sessionName }}</span>
                        </p>
                    </div>

                    <x-filament::badge :color="$view->color()">
                        {{ $view->label() }}
                    </x-filament::badge>
                </div>

                <div class="mt-5 space-y-1">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                        {{ __('admin.whatsapp.account.identity') }}
                    </p>

                    <p class="text-base">
                        @if (filled($identityName) || filled($identityPhone))
                            @if (filled($identityName))
                                <span class="font-semibold">{{ $identityName }}</span>
                            @endif
                            @if (filled($identityPhone))
                                <span dir="ltr" class="font-mono text-gray-500 dark:text-gray-400">{{ $identityPhone }}</span>
                            @endif
                        @elseif ($view->isConnected())
                            <span class="font-semibold">{{ __('admin.whatsapp.account.identity_fallback') }}</span>
                        @else
                            <span class="text-gray-500 dark:text-gray-400">{{ __('admin.whatsapp.account.unknown') }}</span>
                        @endif
                    </p>
                </div>

                <div class="mt-5">
                    <x-filament::callout :color="$view->color()" :description="$view->guidance()" />
                </div>
            </x-filament::card>

            {{-- Secondary: WAHA service diagnostics. --}}
            <x-filament::card>
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-1">
                        <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                            {{ __('admin.whatsapp.service.title') }}
                        </h3>

                        @if ($lastCheckedAt)
                            <p class="text-xs text-gray-400 dark:text-gray-500">
                                {{ __('admin.whatsapp.service.last_checked', ['time' => $lastCheckedAt]) }}
                            </p>
                        @endif
                    </div>

                    <x-filament::badge :color="$serviceColor">
                        {{ $serviceLabel }}
                    </x-filament::badge>
                </div>

                @if (filled($rawStatus))
                    <p class="mt-4 text-xs text-gray-400 dark:text-gray-500">
                        {{ __('admin.whatsapp.account.raw_status', ['status' => $rawStatus]) }}
                    </p>
                @endif
            </x-filament::card>
        </div>

        @if ($view->isQrVisible())
            <x-filament::card>
                <h2 class="text-base font-semibold">
                    {{ __('admin.whatsapp.qr.heading') }}
                </h2>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('admin.whatsapp.qr.instructions') }}
                </p>

                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                    {{ __('admin.whatsapp.qr.rotates') }}
                </p>

                <div
                    class="mt-5"
                    x-data="{
                        src: @js($qrUrl.'?v='.$qrVersion),
                        failed: false,
                        refresh() {
                            this.failed = false
                            this.src = @js($qrUrl).concat('?v=').concat(Date.now())
                        },
                        init() {
                            setInterval(() => this.refresh(), 20000)
                        },
                    }"
                >
                    <div class="mx-auto flex w-fit items-center justify-center rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700">
                        <img
                            src="{{ $qrUrl }}?v={{ $qrVersion }}"
                            x-bind:src="src"
                            x-on:error="failed = true"
                            alt="{{ __('admin.whatsapp.qr.alt') }}"
                            class="h-56 w-56 rounded-lg bg-white object-contain"
                        />
                    </div>

                    <p
                        x-show="failed"
                        x-cloak
                        class="mt-3 text-center text-sm text-danger-600 dark:text-danger-400"
                    >
                        {{ __('admin.whatsapp.qr.unavailable') }}
                    </p>
                </div>
            </x-filament::card>
        @endif
    </div>
</x-filament-panels::page>

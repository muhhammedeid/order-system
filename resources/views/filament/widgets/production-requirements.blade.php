<x-filament-widgets::widget>
    <x-filament::section
        :heading="__('admin.production.heading')"
        :description="__('admin.production.description')"
        icon="heroicon-o-cog-6-tooth"
    >
        @if ($requirements->isEmpty())
            <x-filament::empty-state
                :heading="__('admin.production.empty')"
                icon="heroicon-o-check-circle"
            />
        @else
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($requirements as $requirement)
                    @php
                        $drilldownUrl = \App\Filament\Pages\ProductionRequirements::getUrl(['product' => $requirement['id']]);
                    @endphp

                    <article class="production-card">
                        @if ($requirement['image'])
                            <img
                                src="{{ $requirement['image'] }}"
                                alt="{{ __('admin.production.image_alt', ['product' => $requirement['name']]) }}"
                                loading="lazy"
                                class="h-40 w-full rounded-xl object-cover"
                            >
                        @else
                            <div class="flex h-40 items-center justify-center rounded-xl bg-gray-100 text-gray-400 dark:bg-gray-800">
                                <x-filament::icon icon="heroicon-o-photo" class="h-9 w-9" />
                            </div>
                        @endif

                        <header class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="truncate font-semibold text-gray-950 dark:text-white">
                                    {{ $requirement['name'] }}
                                </h3>
                                <p class="mt-1 text-sm text-gray-500" dir="ltr">{{ $requirement['code'] }}</p>
                            </div>

                            @unless ($requirement['active'])
                                <x-filament::badge color="danger">{{ __('admin.production.inactive') }}</x-filament::badge>
                            @endunless
                        </header>

                        <section class="rounded-xl border border-primary-200 bg-primary-50 p-3 dark:border-primary-900 dark:bg-primary-950/40">
                            <p class="text-xs font-medium text-gray-600 dark:text-gray-300">
                                {{ $requirement['quantity_mode'] === 'per_color'
                                    ? __('admin.production.order_quantity_per_color')
                                    : __('admin.production.total_required') }}
                            </p>
                            <p class="production-quantity mt-2 tabular-nums">
                                {{ number_format($requirement['required_quantity']) }}
                            </p>
                        </section>

                        @if ($requirement['quantity_mode'] === 'per_color' && count($requirement['color_breakdown']))
                            <section aria-label="{{ __('admin.production.colors_breakdown') }}">
                                <p class="mb-2 text-xs font-semibold text-gray-500">
                                    {{ __('admin.production.colors_breakdown') }}
                                </p>
                                <div class="production-breakdown">
                                    @foreach ($requirement['color_breakdown'] as $color)
                                        <div class="production-breakdown-item">
                                            <p class="truncate text-xs text-gray-500">{{ $color['color'] }}</p>
                                            <p class="mt-1 font-bold tabular-nums text-gray-950 dark:text-white">
                                                {{ trans_choice('admin.production.pieces', $color['quantity'], ['count' => number_format($color['quantity'])]) }}
                                            </p>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif

                        <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                            <x-filament::badge color="gray">
                                {{ trans_choice('admin.production.orders_count', $requirement['orders_count'], ['count' => $requirement['orders_count']]) }}
                            </x-filament::badge>

                            <x-filament::button
                                tag="a"
                                :href="$drilldownUrl"
                                size="sm"
                                icon="heroicon-m-arrow-left"
                            >
                                {{ __('admin.production.view_orders') }}
                            </x-filament::button>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>


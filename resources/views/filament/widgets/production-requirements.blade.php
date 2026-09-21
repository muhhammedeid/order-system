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
            <div class="production-grid">
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
                                class="production-card-image"
                            >
                        @else
                            <div class="production-card-image production-card-image-empty">
                                <x-filament::icon icon="heroicon-o-photo" />
                            </div>
                        @endif

                        <header class="production-card-header">
                            <div class="min-w-0">
                                <h3 class="production-card-name">{{ $requirement['name'] }}</h3>
                                <p class="production-card-code" dir="ltr">{{ $requirement['code'] }}</p>
                            </div>

                            @unless ($requirement['active'])
                                <x-filament::badge color="danger">{{ __('admin.production.inactive') }}</x-filament::badge>
                            @endunless
                        </header>

                        <section
                            class="production-quantity-section"
                            aria-labelledby="production-required-{{ $requirement['id'] }}"
                        >
                            <p
                                class="production-section-label"
                                id="production-required-{{ $requirement['id'] }}"
                            >
                                {{ __('admin.production.required_per_color') }}
                            </p>
                            <p class="production-quantity tabular-nums">
                                {{ number_format($requirement['required_quantity']) }}
                            </p>
                        </section>

                        <section class="production-breakdown-section" aria-label="{{ __('admin.production.color_breakdown') }}">
                            <p class="production-section-label">
                                {{ __('admin.production.color_breakdown') }}
                            </p>

                            @if (count($requirement['color_breakdown']))
                                <div class="production-breakdown">
                                    @foreach ($requirement['color_breakdown'] as $color)
                                        <div class="production-breakdown-item">
                                            <p class="production-breakdown-color">{{ $color['color'] }}</p>
                                            <p class="production-breakdown-quantity tabular-nums">
                                                {{ trans_choice('admin.production.pieces', $color['quantity'], ['count' => number_format($color['quantity'])]) }}
                                            </p>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="production-breakdown-empty">{{ __('admin.production.color_breakdown_empty') }}</p>
                            @endif
                        </section>

                        <div class="production-card-footer">
                            <x-filament::badge color="gray">
                                {{ trans_choice('admin.production.orders_count', $requirement['orders_count'], ['count' => $requirement['orders_count']]) }}
                            </x-filament::badge>

                            <x-filament::button
                                tag="a"
                                :href="$drilldownUrl"
                                size="sm"
                                icon="heroicon-m-list-bullet"
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

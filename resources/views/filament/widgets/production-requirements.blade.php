<x-filament-widgets::widget>
    <x-filament::section
        heading="المطلوب للتشغيل حالياً"
        description="الكميات المتبقية من الطلبات المؤكدة وقيد التسليم"
        icon="heroicon-o-cog-6-tooth"
    >
        @if ($requirements->isEmpty())
            <x-filament::empty-state
                heading="لا توجد كميات مطلوبة للتشغيل حالياً"
                icon="heroicon-o-check-circle"
            />
        @else
            <div
                style="display: grid; grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr)); gap: 1rem;"
            >
                @foreach ($requirements as $requirement)
                    @php
                        $drilldownUrl = \App\Filament\Pages\ProductionRequirements::getUrl(['product' => $requirement['id']]);
                    @endphp

                    <div
                        style="display: flex; flex-direction: column; gap: 0.75rem; height: 100%; border: 1px solid var(--gray-200); border-radius: 0.75rem; padding: 0.75rem;"
                    >
                        @if ($requirement['image'])
                            <img
                                src="{{ url('/storage/'.$requirement['image']) }}"
                                alt="صورة {{ $requirement['name'] }}"
                                loading="lazy"
                                style="width: 100%; height: 8.75rem; object-fit: cover; border-radius: 0.5rem;"
                            >
                        @else
                            <div
                                style="display: flex; align-items: center; justify-content: center; height: 8.75rem; border-radius: 0.5rem; background-color: var(--gray-100); color: var(--gray-400);"
                            >
                                <x-filament::icon icon="heroicon-o-photo" style="width: 2rem; height: 2rem;" />
                            </div>
                        @endif

                        <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                            <span style="font-weight: 600; line-height: 1.4;">
                                {{ $requirement['name'] }}
                            </span>
                            <span style="color: var(--gray-500); direction: ltr; text-align: start;">
                                {{ $requirement['code'] }}
                            </span>
                        </div>

                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            <x-filament::badge color="warning">
                                الكمية المطلوبة: {{ $requirement['required_quantity'] }}
                            </x-filament::badge>

                            <x-filament::badge color="gray">
                                {{ $requirement['orders_count'] }} طلب
                            </x-filament::badge>

                            @unless ($requirement['active'])
                                <x-filament::badge color="danger">
                                    غير مفعّل
                                </x-filament::badge>
                            @endunless
                        </div>

                        <div style="margin-top: auto;">
                            <x-filament::button
                                tag="a"
                                :href="$drilldownUrl"
                                size="sm"
                                icon="heroicon-m-list-bullet"
                            >
                                عرض الطلبات المساهمة
                            </x-filament::button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>

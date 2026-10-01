@php
    $icons = [
        'new' => 'heroicon-o-inbox',
        'confirmed' => 'heroicon-o-check-circle',
        'partially_delivered' => 'heroicon-o-truck',
        'delivered' => 'heroicon-o-check-badge',
        'cancelled' => 'heroicon-o-x-circle',
    ];
@endphp

<div class="order-status-cards">
    @foreach ($this->getCachedTabs() as $status => $tab)
        <button
            type="button"
            wire:key="order-status-{{ $status }}"
            wire:click="$set('activeTab', '{{ $status }}')"
            wire:loading.attr="disabled"
            wire:target="activeTab"
            @class(['order-status-card', 'is-active' => $this->activeTab === $status])
            data-status="{{ $status }}"
            aria-pressed="{{ $this->activeTab === $status ? 'true' : 'false' }}"
        >
            <span class="order-status-icon" aria-hidden="true">
                <x-filament::icon :icon="$icons[$status]" />
            </span>
            <span class="order-status-content">
                <span class="order-status-label">{{ $tab->getLabel() }}</span>
                <span class="order-status-count">{{ $tab->getBadge() }}</span>
            </span>
        </button>
    @endforeach
</div>

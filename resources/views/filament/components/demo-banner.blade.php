<div class="text-center text-xs" style="padding: 0.75rem; line-height: 1.7;">
    @if (config('demo.enabled'))
        <p style="font-weight: 700;">{{ __('demo.notice') }}</p>
    @endif
    <p>{{ __('demo.owner') }}</p>
</div>

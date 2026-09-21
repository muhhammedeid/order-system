<form method="POST" action="{{ route('locale.update', app()->getLocale() === 'ar' ? 'en' : 'ar') }}">
    @csrf
    <button
        type="submit"
        aria-label="{{ __('admin.language') }}"
        class="fi-icon-btn fi-size-md"
        title="{{ app()->getLocale() === 'ar' ? __('admin.english') : __('admin.arabic') }}"
    >
        <span class="text-sm font-semibold">{{ app()->getLocale() === 'ar' ? 'EN' : 'ع' }}</span>
    </button>
</form>

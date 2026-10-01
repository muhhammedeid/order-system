<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @php
            $meta = $page['props']['meta'] ?? [];
            $brandName = config('app.name', 'MAI SHOES');
            $defaultDescription = __('storefront.meta.description');
        @endphp
        <title>{{ $meta['title'] ?? $brandName }}</title>
        <meta name="description" content="{{ $meta['description'] ?? $defaultDescription }}">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#FFFFFF">
        <meta name="color-scheme" content="light dark">

        <meta property="og:type" content="{{ $meta['type'] ?? 'website' }}">
        <meta property="og:site_name" content="{{ $brandName }}">
        <meta property="og:title" content="{{ $meta['title'] ?? $brandName }}">
        <meta property="og:description" content="{{ $meta['description'] ?? $defaultDescription }}">
        <meta property="og:url" content="{{ url()->current() }}">
        @isset($meta['image'])
            <meta property="og:image" content="{{ $meta['image'] }}">
            <meta name="twitter:card" content="summary_large_image">
        @endisset

        <link rel="icon" type="image/png" href="/favicon.png?v=20261001">


        <script>
            (function () {
                try {
                    if (localStorage.getItem('mai-theme') !== 'dark') {
                        return;
                    }

                    document.documentElement.classList.add('dark');

                    var meta = document.querySelector('meta[name="theme-color"]');
                    if (meta) {
                        meta.setAttribute('content', '#16222C');
                    }
                } catch (error) {
                    /* theme stays light when storage is unavailable */
                }
            })();
        </script>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link
            href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&family=Reem+Kufi:wght@400..700&display=swap"
            rel="stylesheet"
        >

        @inertiaHead
        @vite(['resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>

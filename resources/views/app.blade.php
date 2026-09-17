<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Wholesale Order System') }}</title>
        <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>&#128556;</text></svg>">
        @routes
        @inertiaHead
        @vite(['resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-white text-gray-900">
        @inertia
    </body>
</html>

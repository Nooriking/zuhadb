<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#070707">

        <title inertia>{{ config('app.name', 'ZUHA PERFUME') }}</title>

        @php
            $shopLogo = \App\Models\AppSetting::bill()['logo'] ?? '/images/logo.png';
            $favicon = str_starts_with($shopLogo, 'http') ? $shopLogo : url('/images/favicon-32.png');
            $appleIcon = url('/images/apple-touch-icon.png');
        @endphp
        <link rel="icon" href="{{ $favicon }}" type="image/png" sizes="32x32">
        <link rel="icon" href="{{ url('/images/favicon-48.png') }}" type="image/png" sizes="48x48">
        <link rel="shortcut icon" href="{{ url('/favicon.png') }}" type="image/png">
        <link rel="apple-touch-icon" href="{{ $appleIcon }}">

        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        {{-- Outfit only on every page; Cormorant loads on auth pages via AuthLayout --}}
        <link href="https://fonts.bunny.net/css?family=outfit:400,500,600&display=swap" rel="stylesheet" />

        {{-- Ziggy routes are bundled via resources/js/ziggy.js (see app.tsx) — no @routes dump --}}
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>

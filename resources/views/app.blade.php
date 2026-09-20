<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        <title inertia>{{ config('app.name', 'UpDown') }}</title>

        <link rel="icon" type="image/png" href="/images/brand/logo-updown.png">
        <link rel="apple-touch-icon" href="/images/brand/logo-updown.png">

        <!-- Satta Matka API domain verification -->
        <script
            async
            src="https://sattamatkaapi.live/sma.js"
            data-sma-verify="{{ config('services.sattamatka.verify') }}"
        ></script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700|syne:500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>

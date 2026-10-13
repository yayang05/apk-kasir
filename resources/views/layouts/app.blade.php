<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-receipt-width="80">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>
        <script>
            (() => {
                const theme = localStorage.getItem('apk-kasir-theme') || 'light';
                const receiptWidth = localStorage.getItem('apk-kasir-receipt-width') || '80';
                const sidebarColor = localStorage.getItem('apk-kasir-sidebar-color') || '#0f172a';
                const sidebarOpacity = localStorage.getItem('apk-kasir-sidebar-opacity') || '0.94';
                const surfaceColor = localStorage.getItem('apk-kasir-surface-color') || '#ffffff';
                const surfaceOpacity = localStorage.getItem('apk-kasir-surface-opacity') || '1';
                const hex = sidebarColor.replace('#', '');
                const rgb = hex.length === 3 ? hex.split('').map((value) => parseInt(value + value, 16)) : [parseInt(hex.slice(0, 2), 16), parseInt(hex.slice(2, 4), 16), parseInt(hex.slice(4, 6), 16)];
                document.documentElement.style.setProperty('--sidebar-bg', `rgba(${rgb.join(',')}, ${sidebarOpacity})`);
                const surfaceHex = surfaceColor.replace('#', '');
                const surfaceRgb = surfaceHex.length === 3 ? surfaceHex.split('').map((value) => parseInt(value + value, 16)) : [parseInt(surfaceHex.slice(0, 2), 16), parseInt(surfaceHex.slice(2, 4), 16), parseInt(surfaceHex.slice(4, 6), 16)];
                document.documentElement.style.setProperty('--surface-bg', `rgba(${surfaceRgb.join(',')}, ${surfaceOpacity})`);
                document.documentElement.classList.toggle('theme-dark', theme === 'dark');
                document.documentElement.classList.toggle('theme-transparent', theme === 'transparent');
                document.documentElement.dataset.receiptWidth = receiptWidth === '58' ? '58' : '80';
            })();
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
            <div class="app-frame min-h-screen text-slate-900">
            <div class="print:hidden">@include('layouts.navigation')</div>

            <div class="min-w-0 flex-1">
                @isset($header)
                    <header class="app-page-header border-b border-slate-200/80 bg-white/90 shadow-sm backdrop-blur-xl">
                        <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>

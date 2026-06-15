<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#f8fafc">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <script>
            (() => {
                const theme = localStorage.getItem('coreti-theme') || 'system';
                const resolved = theme === 'system'
                    ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                    : theme;

                document.documentElement.classList.toggle('dark', resolved === 'dark');
                document.documentElement.dataset.theme = theme;
            })();
        </script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @include('layouts.partials.button-theme')
    </head>
    <body class="site-body font-sans text-gray-900 antialiased">
        @include('layouts.partials.toasts')
        <div
            class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-cover bg-center"
            style="background-image: url('{{ asset('images/login-bg.jpg') }}');"
        >
            <div>
                <a href="/">
                    <x-application-logo class="login-logo drop-shadow-[0_8px_30px_rgba(0,0,0,0.25)]" />
                </a>
            </div>

            {{-- Caixa do formulário: vidro fosco + borda bonita + mais espaço no topo --}}
            <div class="w-full sm:max-w-md mt-6 p-[1px] rounded-2xl
                        bg-gradient-to-br from-white/60 to-white/10
                        shadow-2xl shadow-black/10">
                <div class="px-6 pt-8 pb-6 rounded-2xl
                            bg-white/15 backdrop-blur-xl
                            border border-white/60 ring-2 ring-black/10">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>

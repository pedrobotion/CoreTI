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
                localStorage.setItem('coreti-theme', 'light');
                document.documentElement.classList.remove('dark');
                document.documentElement.dataset.theme = 'light';
            })();
        </script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @include('layouts.partials.button-theme')
    </head>
    <body class="site-body bg-slate-100 font-sans antialiased">
        <div class="min-h-screen">
            @include('layouts.navigation')
            @include('layouts.partials.feedback-modal')
            @include('layouts.partials.confirm-dialog')

            <div class="content-shell">
                <!-- Page Content -->
                <main class="p-4 sm:p-6 lg:p-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
        <script>
            document.addEventListener('submit', function (event) {
                const form = event.target;
                if (!(form instanceof HTMLFormElement)) return;
                const message = form.dataset.confirmMessage;
                if (!message || form.dataset.confirmed === '1') return;
                event.preventDefault();
                window.dispatchEvent(new CustomEvent('coreti-confirm', { detail: { message, form } }));
            }, true);
        </script>
    </body>
</html>

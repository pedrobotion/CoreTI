<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Acesso negado</title>

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="bg-gray-100 text-gray-800">
        <div class="min-h-screen flex items-center justify-center p-6">
            <div class="max-w-md w-full bg-white shadow-sm rounded-lg p-6">
                <div class="text-sm text-gray-500 mb-2">Erro 403</div>
                <h1 class="text-2xl font-semibold mb-3">Acesso negado</h1>
                <p class="text-gray-600 mb-6">Você não tem permissão para acessar esta página.</p>

                <div class="flex items-center gap-3">
                    <a href="{{ route('home') }}" class="inline-flex items-center px-4 py-2 bg-gray-900 text-white rounded-md hover:bg-gray-800">
                        Voltar para o início
                    </a>
                    <a href="{{ url()->previous() }}" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-800 rounded-md hover:bg-gray-200">
                        Voltar
                    </a>
                </div>
            </div>
        </div>
    </body>
</html>

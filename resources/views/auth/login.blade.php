<x-guest-layout>
    <div class="login-card__head">
        <h2 class="login-card__title">Acesse sua conta</h2>
        <p class="login-card__sub">Entre com suas credenciais do CoreTI.</p>
    </div>

    <!-- Session Status -->
    @if (session('status'))
        <div class="mb-4 text-sm font-semibold text-white bg-emerald-600 px-4 py-3 rounded-lg shadow ring-1 ring-black/10">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="login-form">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="nome@cocari.com.br" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Senha')" />
            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password"
                            placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me + Esqueceu a senha -->
        <div class="mt-4 flex items-center justify-between gap-3">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-sky-600 shadow-sm focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-900" name="remember">
                <span class="ms-2 text-sm text-gray-600 dark:text-slate-300">{{ __('Lembrar-me') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm font-medium text-sky-700 hover:text-sky-900 dark:text-sky-400 dark:hover:text-sky-300 focus:outline-none focus:underline"
                   href="{{ route('password.request') }}">
                    {{ __('Esqueceu a senha?') }}
                </a>
            @endif
        </div>

        <x-primary-button class="mt-6 w-full justify-center py-2.5 text-sm">
            {{ __('Entrar') }}
        </x-primary-button>

        @if (Route::has('register'))
            <p class="mt-6 text-center text-sm text-gray-500 dark:text-slate-400">
                {{ __('Não tem acesso?') }}
                <a href="{{ route('register') }}" class="font-semibold text-sky-700 hover:text-sky-900 dark:text-sky-400 dark:hover:text-sky-300">
                    {{ __('Criar conta') }}
                </a>
            </p>
        @endif
    </form>
</x-guest-layout>

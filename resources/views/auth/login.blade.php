<x-guest-layout>
    <!-- Session Status -->
@if (session('status'))
    <div class="mb-4 text-sm font-semibold
                text-white bg-emerald-600 px-4 py-3
                rounded-lg shadow ring-1 ring-black/10">
        {{ session('status') }}
    </div>
@endif


    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Senha')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Lembrar-me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-between mt-4">

            <a href="{{ route('register') }}"
            class="inline-flex items-center px-4 py-2 bg-white/40 backdrop-blur
                    border border-gray-300 rounded-md font-semibold text-xs text-gray-800
                    hover:bg-white/70 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                {{ __('Criar conta') }}
            </a>

            <div class="flex items-center">
                @if (Route::has('password.request'))
                    <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    href="{{ route('password.request') }}">
                        {{ __('Esqueceu a Senha?') }}
                    </a>
                @endif

                <x-primary-button class="ms-3">
                    {{ __('Login') }}
                </x-primary-button>
            </div>
        </div>
    </form>
</x-guest-layout>

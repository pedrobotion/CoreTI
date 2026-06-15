@php
    $feedbackErrors = $errors->all();
    $feedbackType = null;
    $feedbackTitle = null;
    $feedbackMessages = [];

    if (!empty($feedbackErrors)) {
        $feedbackType = 'error';
        $feedbackTitle = 'Não foi possível concluir a ação';
        $feedbackMessages = $feedbackErrors;
    } elseif (session('error')) {
        $feedbackType = 'error';
        $feedbackTitle = 'Não foi possível concluir a ação';
        $feedbackMessages = [(string) session('error')];
    } elseif (session('success')) {
        $feedbackType = 'success';
        $feedbackTitle = 'Ação concluída com sucesso';
        $feedbackMessages = [(string) session('success')];
    }
@endphp

@if($feedbackType && !empty($feedbackMessages))
    <div
        x-data="{ open: true }"
        x-cloak
        x-show="open"
        x-transition.opacity
        class="fixed inset-0 z-[10000] flex items-center justify-center bg-slate-950/50 px-4"
        @keydown.escape.window="open = false"
    >
        <div class="w-full max-w-xl rounded-xl border bg-white p-5 shadow-2xl" :class="{
            'border-red-200': @js($feedbackType === 'error'),
            'border-emerald-200': @js($feedbackType === 'success')
        }">
            <h3 class="text-lg font-bold" :class="{
                'text-red-700': @js($feedbackType === 'error'),
                'text-emerald-700': @js($feedbackType === 'success')
            }">{{ $feedbackTitle }}</h3>

            <div class="mt-3 space-y-2 text-sm text-slate-700 max-h-72 overflow-y-auto pr-1">
                @foreach($feedbackMessages as $message)
                    <p>• {{ $message }}</p>
                @endforeach
            </div>

            <div class="mt-5 flex justify-end">
                <button type="button" @click="open = false" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Entendi
                </button>
            </div>
        </div>
    </div>
@endif

<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">CoreTI</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">Adicionar Aplicativo</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-300">
                    Cadastre instaladores individuais ou kits com múltiplos arquivos.
                </p>
            </div>

            <a href="{{ route('applications.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800">
                Voltar
            </a>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form method="POST" action="{{ route('applications.store') }}" enctype="multipart/form-data" class="grid gap-5">
                @csrf

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Nome</label>
                        <input name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Categoria</label>
                        <input name="category" value="{{ old('category') }}" list="application-categories" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                        <datalist id="application-categories">
                            @foreach ($categories as $category)
                                <option value="{{ $category }}"></option>
                            @endforeach
                        </datalist>
                        <x-input-error :messages="$errors->get('category')" class="mt-2" />
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Foto da aplicação</label>
                    <input type="file" name="image" accept="image/*" required class="mt-1 block w-full rounded-md border border-slate-300 bg-white text-sm text-slate-700 shadow-sm file:mr-4 file:min-h-10 file:border-0 file:bg-slate-900 file:px-4 file:text-sm file:font-semibold file:text-white hover:file:bg-slate-800 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                    <x-input-error :messages="$errors->get('image')" class="mt-2" />
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Arquivos para download</label>
                    <input type="file" name="files[]" multiple required class="mt-1 block w-full rounded-md border border-slate-300 bg-white text-sm text-slate-700 shadow-sm file:mr-4 file:min-h-10 file:border-0 file:bg-slate-900 file:px-4 file:text-sm file:font-semibold file:text-white hover:file:bg-slate-800 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                    <x-input-error :messages="$errors->get('files')" class="mt-2" />
                    <x-input-error :messages="$errors->get('files.*')" class="mt-2" />
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                        Salvar aplicativo
                    </button>
                    <a href="{{ route('applications.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:hover:bg-slate-800">
                        Cancelar
                    </a>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>

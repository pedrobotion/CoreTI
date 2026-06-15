<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">CoreTI</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">Aplicativos</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-300">
                    Repositório de instaladores, drivers e pacotes usados pelo time.
                </p>
            </div>

            @if (auth()->user()?->role === 'admin')
                <a href="{{ route('applications.create') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                    Adicionar aplicativo
                </a>
            @endif
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form method="GET" action="{{ route('applications.index') }}" class="toolbar-search-grid">
                <select name="category" class="toolbar-select text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    <option value="">Todas as categorias</option>
                    @foreach ($categories as $item)
                        <option value="{{ $item }}" @selected($category === $item)>{{ $item }}</option>
                    @endforeach
                </select>
                <input name="q" value="{{ $search }}" placeholder="Buscar aplicativo, arquivo ou categoria" class="toolbar-input rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                <button type="submit" class="toolbar-btn-primary inline-flex items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Buscar</button>
                <a href="{{ route('applications.index') }}" class="toolbar-btn inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Limpar</a>
            </form>
        </section>

        <section>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @forelse ($applications as $application)
                    <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex gap-4">
                            <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-md border border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-950">
                                @if ($application->imageUrl())
                                    <img src="{{ $application->imageUrl() }}" alt="{{ $application->name }}" class="h-full w-full object-contain p-2">
                                @else
                                    <span class="text-xs font-semibold uppercase text-slate-500">{{ $application->file_extension }}</span>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <h2 class="truncate text-base font-semibold text-slate-950 dark:text-white">{{ $application->name }}</h2>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $application->category ?? 'Geral' }}</p>
                                <p class="mt-2 truncate text-xs text-slate-500 dark:text-slate-400">{{ $application->is_bundle ? count($application->bundle_files ?? []) . ' arquivos' : $application->file_name }}</p>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-100 pt-4 text-sm dark:border-slate-800">
                            <span class="text-slate-500 dark:text-slate-400">{{ $application->displaySize() }}</span>
                            @if ($application->fileExists())
                                <a href="{{ route('applications.download', $application) }}" class="inline-flex min-h-9 items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-3 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                                    {{ $application->downloadLabel() }}
                                </a>
                            @else
                                <button disabled class="inline-flex min-h-9 items-center justify-center rounded-md border border-slate-300 bg-slate-100 px-3 text-sm font-semibold text-slate-500 shadow-sm cursor-not-allowed" title="Arquivo não disponível">
                                    Arquivo indisponível
                                </button>

                                @if (auth()->user()?->role === 'admin')
                                    <a href="{{ route('applications.create') }}" class="ml-2 inline-flex min-h-9 items-center justify-center rounded-md border border-amber-700 bg-amber-50 px-3 text-sm font-semibold text-amber-700 shadow-sm hover:bg-amber-100">
                                        Reimportar
                                    </a>
                                @endif
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="rounded-lg border border-slate-200 bg-white px-5 py-8 text-center text-sm text-slate-500 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400 sm:col-span-2 xl:col-span-3">
                        Nenhum aplicativo encontrado.
                    </div>
                @endforelse
            </div>

            <div class="mt-5">
                {{ $applications->links() }}
            </div>
        </section>
    </div>
</x-app-layout>

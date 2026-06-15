<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Unidade Digital</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-950">Atalhos de operação</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-600">
                Acesso rápido aos portais, formulários e bases de apoio usados no atendimento diário da Unidade Digital.
            </p>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($links as $link)
                    <a
                        href="{{ $link['href'] }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="group rounded-lg border border-slate-200 bg-slate-50 p-4 transition hover:border-slate-300 hover:bg-white"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-slate-900 group-hover:text-slate-950">{{ $link['title'] }}</p>
                            <span
                                data-link-status="{{ $link['id'] }}"
                                class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2 py-0.5 text-xs font-medium text-slate-500"
                            >
                                <span class="h-2 w-2 rounded-full bg-slate-300"></span>
                                Verificando
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-slate-600">{{ $link['description'] }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    </div>

    <script>
        async function loadUnidadeDigitalLinkStatus() {
            try {
                const response = await fetch(@js(route('unidade-digital.status')), { headers: { Accept: 'application/json' } });
                if (!response.ok) return;

                const payload = await response.json();
                const items = payload.items || {};

                Object.entries(items).forEach(([id, info]) => {
                    const el = document.querySelector(`[data-link-status="${id}"]`);
                    if (!el) return;

                    const online = !!info.online;
                    const dotClass = online ? 'bg-emerald-500' : 'bg-rose-500';
                    const label = online ? 'Online' : 'Offline';

                    el.className = `inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium ${online ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700'}`;
                    el.innerHTML = `<span class="h-2 w-2 rounded-full ${dotClass}"></span>${label}`;
                });
            } catch (_) {}
        }

        document.addEventListener('DOMContentLoaded', loadUnidadeDigitalLinkStatus);
    </script>
</x-app-layout>

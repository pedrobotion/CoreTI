<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Regras de sugestão', 'description' => 'Sugestões de serviço configuráveis. Nenhuma regra aplica alterações automaticamente.'])
        @if(session('success'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Nova regra</h2>
            <form method="POST" action="{{ route('cmdb.service-rules.store') }}" class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                @csrf
                <input name="pattern" required placeholder="Texto ou expressão" class="rounded-md border-slate-300 text-sm">
                <select name="match_type" class="rounded-md border-slate-300 text-sm"><option value="contains">Contém</option><option value="starts_with">Começa com</option><option value="ends_with">Termina com</option><option value="exact">Exato</option><option value="regex">Regex</option></select>
                <select name="service_id" required class="rounded-md border-slate-300 text-sm"><option value="">Serviço sugerido</option>@foreach($services as $service)<option value="{{ $service->id }}">{{ $service->name }}</option>@endforeach</select>
                <input name="priority" type="number" min="0" value="100" required placeholder="Prioridade" class="rounded-md border-slate-300 text-sm">
                <button class="rounded-md bg-[#033151] px-4 py-2 text-sm font-semibold text-white">Adicionar regra</button>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-100 text-sm"><thead class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400"><tr><th class="px-5 py-3">Prioridade</th><th class="px-5 py-3">Padrão</th><th class="px-5 py-3">Correspondência</th><th class="px-5 py-3">Serviço</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Ações</th></tr></thead><tbody class="divide-y divide-slate-100">
                @forelse($rules as $rule)
                    <tr><form method="POST" action="{{ route('cmdb.service-rules.update', $rule) }}">@csrf @method('PATCH')<td class="px-5 py-3"><input name="priority" value="{{ $rule->priority }}" class="w-20 rounded border-slate-300 text-sm"></td><td class="px-5 py-3"><input name="pattern" value="{{ $rule->pattern }}" class="w-full min-w-40 rounded border-slate-300 text-sm"></td><td class="px-5 py-3"><select name="match_type" class="rounded border-slate-300 text-sm">@foreach(['contains'=>'Contém','starts_with'=>'Começa com','ends_with'=>'Termina com','exact'=>'Exato','regex'=>'Regex'] as $key=>$label)<option value="{{ $key }}" @selected($rule->match_type === $key)>{{ $label }}</option>@endforeach</select></td><td class="px-5 py-3"><select name="service_id" class="rounded border-slate-300 text-sm">@foreach($services as $service)<option value="{{ $service->id }}" @selected($rule->service_id === $service->id)>{{ $service->name }}</option>@endforeach</select></td><td class="px-5 py-3"><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $rule->active ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200' : 'bg-slate-100 text-slate-600' }}">{{ $rule->active ? 'Ativa' : 'Inativa' }}</span></td><td class="px-5 py-3"><div class="flex flex-wrap gap-2"><button class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700">Salvar</button></form><form method="POST" action="{{ route('cmdb.service-rules.toggle', $rule) }}">@csrf @method('PATCH')<button class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700">{{ $rule->active ? 'Desativar' : 'Ativar' }}</button></form></div></td></tr>
                @empty<tr><td colspan="6" class="px-5 py-6 text-center text-slate-500">Nenhuma regra cadastrada.</td></tr>@endforelse
            </tbody></table></div>
        </section>
    </div>
</x-app-layout>

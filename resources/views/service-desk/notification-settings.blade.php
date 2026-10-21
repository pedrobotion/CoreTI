<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Service Desk</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Destinatários de notificações</h1>
                <p class="mt-1 text-sm text-slate-500">Defina quem recebe cada tipo de alerta automático do CoreTI.</p>
            </div>
            <a href="{{ route('service-desk.settings') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Voltar</a>
        </div>

        @if(session('success'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif

        <section class="grid gap-6 lg:grid-cols-[minmax(0,360px)_minmax(0,1fr)]">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Novo destinatário</h2>
                <p class="mt-1 text-sm text-slate-500">Cadastre uma pessoa ou caixa compartilhada.</p>
                <form method="POST" action="{{ route('service-desk.notification-settings.recipients.store') }}" class="mt-5 space-y-4">
                    @csrf
                    <label class="block text-sm font-medium text-slate-700">Nome
                        <input name="name" required maxlength="120" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="Ex.: Pedro Bottion">
                    </label>
                    <label class="block text-sm font-medium text-slate-700">E-mail
                        <input name="email" type="email" required maxlength="255" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="nome@cocari.com.br">
                    </label>
                    <button style="background-color: #033151; border-color: #033151;" class="w-full rounded-lg px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">Adicionar destinatário</button>
                </form>

                <div class="mt-7 border-t border-slate-200 pt-5">
                    <h2 class="text-base font-semibold text-slate-900">Destinatários cadastrados</h2>
                    <div class="mt-3 max-h-[520px] space-y-3 overflow-y-auto pr-2">
                        @forelse($recipients as $recipient)
                            <div class="rounded-md border border-slate-200 p-3 {{ $recipient->active ? '' : 'opacity-60' }}">
                                <form method="POST" action="{{ route('service-desk.notification-settings.recipients.update', $recipient) }}" class="space-y-2">
                                    @csrf @method('PATCH')
                                    <input name="name" value="{{ $recipient->name }}" required class="w-full rounded border-slate-300 text-sm">
                                    <input name="email" type="email" value="{{ $recipient->email }}" required class="w-full rounded border-slate-300 text-sm">
                                    <div class="flex flex-wrap gap-2">
                                        <button class="rounded border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Salvar</button>
                                        <button type="submit" form="toggle-recipient-{{ $recipient->id }}" class="rounded border {{ $recipient->active ? 'border-amber-300 text-amber-700' : 'border-emerald-300 text-emerald-700' }} px-3 py-1.5 text-xs font-semibold">{{ $recipient->active ? 'Desativar' : 'Ativar' }}</button>
                                    </div>
                                </form>
                                <form id="toggle-recipient-{{ $recipient->id }}" method="POST" action="{{ route('service-desk.notification-settings.recipients.toggle', $recipient) }}">@csrf @method('PATCH')</form>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">Nenhum destinatário cadastrado. O sistema usará o remetente configurado no ambiente.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Roteamento dos avisos</h2>
                        <p class="mt-1 text-sm text-slate-500">Marque os destinatários que devem receber cada evento.</p>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-inset ring-blue-200">{{ $rules->count() }} tipos</span>
                </div>
                @if($recipients->isEmpty())
                    <div class="mt-5 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Cadastre ao menos um destinatário para configurar o roteamento.</div>
                @else
                    <form method="POST" action="{{ route('service-desk.notification-settings.rules.update') }}" class="mt-5">
                        @csrf @method('PUT')
                        <div class="overflow-x-auto rounded-md border border-slate-200">
                            <table class="min-w-full text-left text-sm">
                                <thead class="border-b border-slate-100 text-xs font-semibold uppercase tracking-wide text-slate-400">
                                    <tr><th class="px-4 py-3">Evento</th>@foreach($recipients as $recipient)<th class="px-4 py-3 text-center">{{ $recipient->name }}</th>@endforeach</tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    @foreach($rules as $rule)
                                        <tr class="align-top">
                                            <td class="px-4 py-4"><div class="font-semibold text-slate-800">{{ $rule->label }}</div><div class="mt-1 max-w-md text-xs text-slate-500">{{ $rule->description }}</div></td>
                                            @foreach($recipients as $recipient)
                                                <td class="px-4 py-4 text-center"><input type="checkbox" name="rules[{{ $rule->event_key }}][]" value="{{ $recipient->id }}" @checked($rule->recipients->contains('id', $recipient->id)) class="rounded border-slate-300 text-slate-900"></td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-5 flex justify-end"><button style="background-color: #033151; border-color: #033151;" class="rounded-lg px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90">Salvar roteamento</button></div>
                    </form>
                @endif
            </div>
        </section>
    </div>
</x-app-layout>

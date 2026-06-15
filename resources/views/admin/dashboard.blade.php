<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Painel de Administração') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-semibold mb-4">Gerenciamento de Usuários</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">E-mail</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Permissão</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Módulos</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($users as $user)
                                    <tr class="{{ $user->id === auth()->id() ? 'bg-gray-50' : '' }}">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ $user->name }}
                                            @if($user->id === auth()->id())
                                                <span class="ms-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" style="background-color:#033151;color:#fff;">
                                                    Você
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $user->email }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            @if($user->is_active)
                                                <span class="px-2 inline-flex text-sm leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    Aprovado
                                                </span>
                                            @else
                                                <span class="px-2 inline-flex text-sm leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                    Pendente
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            @if($user->role === 'admin')
                                                <span class="px-2 inline-flex text-sm leading-5 font-semibold rounded-full bg-purple-100 text-purple-800">
                                                    Admin
                                                </span>
                                                @if($user->isMasterAccount())
                                                    <span class="ml-2 px-2 inline-flex text-sm leading-5 font-semibold rounded-full bg-emerald-100 text-emerald-800">
                                                        Master
                                                    </span>
                                                @endif
                                            @else
                                                <span class="px-2 inline-flex text-sm leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                    Usuário
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-700">
                                            @if($user->isMasterAccount())
                                                <span class="text-xs font-semibold text-emerald-700">Acesso total protegido</span>
                                            @elseif($user->role === 'admin')
                                                <span class="text-xs font-semibold text-purple-700">Acesso total</span>
                                            @else
                                                @php
                                                    $perm = $user->modulePermissions;
                                                @endphp
                                                <form action="{{ route('admin.update-module-access', $user->id) }}" method="POST" class="space-y-2">
                                                    @csrf
                                                    @method('PATCH')
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <label class="inline-flex items-center gap-2 text-xs">
                                                            <input type="checkbox" name="servicedesk" value="1" @checked((bool) ($perm->servicedesk ?? false))>
                                                            ServiceDesk
                                                        </label>
                                                        <label class="inline-flex items-center gap-2 text-xs">
                                                            <input type="checkbox" name="unidades" value="1" @checked((bool) ($perm->unidades ?? false))>
                                                            Unidades/Circuitos
                                                        </label>
                                                        <label class="inline-flex items-center gap-2 text-xs">
                                                            <input type="checkbox" name="aplicativos" value="1" @checked((bool) ($perm->aplicativos ?? false))>
                                                            Aplicativos
                                                        </label>
                                                        <label class="inline-flex items-center gap-2 text-xs">
                                                            <input type="checkbox" name="bancada" value="1" @checked((bool) ($perm->bancada ?? false))>
                                                            Bancada
                                                        </label>
                                                        <label class="inline-flex items-center gap-2 text-xs">
                                                            <input type="checkbox" name="administrativo" value="1" @checked((bool) ($perm->administrativo ?? false))>
                                                            Administrativo/Jira
                                                        </label>
                                                    </div>
                                                    <button type="submit" class="btn-coreti-secondary text-xs">
                                                        Salvar módulos
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-sm font-medium">
                                            @if($user->id !== auth()->id() && ! $user->isMasterAccount())
                                                <div class="flex flex-wrap gap-3">
                                                    @can('approve', $user)
                                                    @if(!$user->is_active)
                                                        <form action="{{ route('admin.approve', $user->id) }}" method="POST" class="inline">
                                                            @csrf
                                                            <button type="submit" class="btn-coreti-primary">
                                                                Aprovar
                                                            </button>
                                                        </form>
                                                    @endif
                                                    @endcan
                                                    
                                                    @can('updateRole', $user)
                                                        <form action="{{ route('admin.update-role', $user->id) }}" method="POST" class="inline" data-confirm-message="Deseja alterar a permissão deste usuário?">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button type="submit" class="btn-coreti-secondary" style="min-width:140px;">
                                                                {{ $user->role === 'admin' ? 'Remover Admin' : 'Tornar Admin' }}
                                                            </button>
                                                        </form>
                                                    @endcan
                                                    
                                                    @can('resetPassword', $user)
                                                        <form action="{{ route('admin.reset-password', $user->id) }}" method="POST" class="inline" data-confirm-message="Tem certeza que deseja iniciar o reset de senha deste usuário?">
                                                            @csrf
                                                            <button type="submit" class="btn-coreti-secondary">
                                                                Iniciar Reset de Senha
                                                            </button>
                                                        </form>
                                                    @endcan
                                                    
                                                    @can('delete', $user)
                                                        <form action="{{ route('admin.delete', $user->id) }}" method="POST" class="inline" data-confirm-message="Tem certeza que deseja deletar este usuário?">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn-coreti-danger">
                                                                Deletar
                                                            </button>
                                                        </form>
                                                    @endcan
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if (session('password_reset'))
                @php
                    $reset = session('password_reset');
                @endphp
                <div x-data="{ open: true }" x-show="open" style="position:fixed; top:16px; left:50%; transform:translateX(-50%); z-index:50;">
                    <div class="w-[480px] max-w-[92vw] rounded-lg bg-white border border-gray-200 shadow-lg p-4">
                        <div class="text-sm font-semibold text-gray-900">Reset de senha iniciado</div>
                        <div class="mt-2 text-sm text-gray-600">Envie este link para o usuário concluir a troca:</div>
                        <div class="mt-3 flex items-center gap-2">
                            <input type="text" readonly value="{{ $reset['link'] }}" class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm bg-gray-50 text-gray-900" />
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                @click="navigator.clipboard.writeText({{ Js::from($reset['link']) }})"
                            >
                                Copiar
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">Usuário: {{ $reset['email'] }}</p>
                        <div class="mt-3 flex justify-end">
                            <button type="button" class="text-sm text-gray-500 hover:text-gray-700" @click="open = false">Fechar</button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

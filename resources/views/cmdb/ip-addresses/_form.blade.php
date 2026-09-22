@php
    $ipAddress = $ipAddress ?? null;
    $asset = $asset ?? null;
    $isEdit = (bool) $ipAddress;
@endphp

<section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="text-base font-semibold text-slate-900">{{ $isEdit ? 'Editar endereço IP' : 'Novo endereço IP' }}</h2>

    <form method="POST" action="{{ $isEdit ? route('cmdb.ip-addresses.update', $ipAddress) : route('cmdb.ip-addresses.store') }}" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <div class="md:col-span-2 xl:col-span-3">
            <label class="text-sm font-medium text-slate-700">Ativo</label>
            <select name="asset_id" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                <option value="">Selecione</option>
                @foreach($assets as $item)
                    <option value="{{ $item->id }}" @selected((string) old('asset_id', $asset?->id ?? $ipAddress?->asset_id ?? '') === (string) $item->id)>
                        {{ $item->name }}{{ $item->hostname ? ' - ' . $item->hostname : '' }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="text-sm font-medium text-slate-700">Endereço IP</label>
            <input name="ip_address" value="{{ old('ip_address', $ipAddress->ip_address ?? '') }}" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>

        <div>
            <label class="text-sm font-medium text-slate-700">Máscara</label>
            <input name="subnet_mask" value="{{ old('subnet_mask', $ipAddress->subnet_mask ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>

        <div>
            <label class="text-sm font-medium text-slate-700">Gateway</label>
            <input name="gateway" value="{{ old('gateway', $ipAddress->gateway ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>

        <div>
            <label class="text-sm font-medium text-slate-700">DNS</label>
            <input name="dns" value="{{ old('dns', $ipAddress->dns ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>

        <div>
            <label class="text-sm font-medium text-slate-700">VLAN</label>
            <input name="vlan" value="{{ old('vlan', $ipAddress->vlan ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>

        <div>
            <label class="text-sm font-medium text-slate-700">MAC Address</label>
            <input name="mac_address" value="{{ old('mac_address', $ipAddress->mac_address ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>

        <label class="flex items-center gap-2 text-sm font-medium text-slate-700 md:pt-8">
            <input type="checkbox" name="is_primary" value="1" @checked(old('is_primary', $ipAddress->is_primary ?? false)) class="rounded border-slate-300 text-[#033151]">
            IP Principal
        </label>

        <div class="md:col-span-2 xl:col-span-3">
            <label class="text-sm font-medium text-slate-700">Observações</label>
            <textarea name="notes" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-sm">{{ old('notes', $ipAddress->notes ?? '') }}</textarea>
        </div>

        <div class="md:col-span-2 xl:col-span-3 flex justify-end gap-2">
            <a href="{{ $isEdit ? route('cmdb.assets.show', $ipAddress->asset_id) : route('cmdb.ip-addresses.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</a>
            <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">
                {{ $isEdit ? 'Salvar alterações' : 'Salvar IP' }}
            </button>
        </div>
    </form>
</section>

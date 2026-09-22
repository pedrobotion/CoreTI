@php
    $isEdit = $isEdit ?? false;
@endphp

<form method="POST" action="{{ $isEdit ? route('cmdb.sync-jobs.update', $job) : route('cmdb.sync-jobs.store') }}" class="space-y-6">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div>
            <label class="text-sm font-medium text-slate-700">Nome</label>
            <input name="name" value="{{ old('name', $job->name ?? '') }}" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Conector</label>
            <select name="connector_id" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                <option value="">Selecione</option>
                @foreach($connectors as $connector)
                    <option value="{{ $connector->id }}" @selected((string) old('connector_id', $job->connector_id ?? '') === (string) $connector->id)>{{ $connector->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Tipo de agendamento</label>
            <select name="schedule_type" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                @foreach($scheduleTypes as $key => $label)
                    <option value="{{ $key }}" @selected(old('schedule_type', $job->schedule_type ?? 'manual') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Frequência em minutos</label>
            <input type="number" min="1" name="frequency_minutes" value="{{ old('frequency_minutes', $job->frequency_minutes ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Horário de execução</label>
            <input type="time" name="run_at" value="{{ old('run_at', $job->run_at ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>
        <div class="flex items-center gap-2 md:pt-8">
            <input id="job_is_active" type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-[#033151]" @checked(old('is_active', $job->is_active ?? false))>
            <label for="job_is_active" class="text-sm font-medium text-slate-700">Ativo</label>
        </div>
        <div class="md:col-span-2 xl:col-span-3">
            <label class="text-sm font-medium text-slate-700">Configurações adicionais (JSON)</label>
            <textarea name="settings" rows="4" class="mt-1 w-full rounded-md border-slate-300 text-sm">{{ old('settings', isset($job->settings) ? json_encode($job->settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '') }}</textarea>
        </div>
    </div>

    <div class="flex justify-end gap-2">
        <a href="{{ route('cmdb.sync-jobs.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Voltar</a>
        <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">{{ $isEdit ? 'Salvar alterações' : 'Salvar job' }}</button>
    </div>
</form>

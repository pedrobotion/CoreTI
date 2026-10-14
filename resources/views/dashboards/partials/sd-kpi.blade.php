@php
    $cor = $cor ?? 'text-sky-400';
    $small = $small ?? false;
@endphp
<div class="rounded-lg border border-white/10 bg-white/[0.03] p-3 transition hover:border-sky-400/30 hover:bg-white/[0.05]">
    <div class="mb-2 inline-flex h-7 w-7 items-center justify-center rounded-md border border-white/10 text-sky-400">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h10"/></svg>
    </div>
    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $titulo }}</p>
    <p class="mt-0.5 font-extrabold {{ $cor }} {{ $small ? 'text-base leading-tight' : 'text-2xl' }}">{{ $valor }}</p>
    @if (!empty($nota))
        <p class="mt-0.5 text-[11px] text-slate-500">{{ $nota }}</p>
    @endif
</div>

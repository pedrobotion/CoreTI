@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => '
        mb-4 text-sm font-semibold
        text-white bg-emerald-600 px-4 py-3
        rounded-lg shadow ring-1 ring-black/10
    ']) }}>
        {{ $status }}
    </div>
@endif

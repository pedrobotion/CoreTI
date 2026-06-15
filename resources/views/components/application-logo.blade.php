@php
    $class = trim((string) $attributes->get('class', ''));
@endphp

<span {{ $attributes->except('class')->merge(['class' => 'inline-flex items-center']) }}>
    <img
        src="{{ asset('images/branding/cocari-logo-black.png') }}"
        alt="Cocari"
        class="cocari-logo cocari-logo--black {{ $class !== '' ? $class : 'h-10 w-auto' }}"
    >
</span>

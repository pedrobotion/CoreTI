<div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-100">
    <div class="p-6">
        <label class="block text-sm font-medium text-gray-700 mb-2">{{ $label }}</label>
        <select class="w-full rounded-md border-gray-200" onchange="if (this.value) window.location.href = this.value;">
            <option value="">Selecione uma opção</option>
            @foreach ($options as $option)
                <option value="{{ $option['href'] }}">{{ $option['title'] }}</option>
            @endforeach
        </select>
    </div>
</div>

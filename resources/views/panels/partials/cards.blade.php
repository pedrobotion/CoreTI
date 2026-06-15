<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    @foreach ($cards as $card)
        @php
            $hasLink = isset($card['href']) && $card['href'];
        @endphp
        @if ($hasLink)
            <a href="{{ $card['href'] }}" class="block">
        @endif
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg hover:shadow-lg transition-shadow duration-300">
            <div class="p-6 text-gray-900">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold mb-2">{{ $card['title'] }}</h3>
                        <p class="text-sm text-gray-600">{{ $card['description'] }}</p>
                    </div>
                    <svg class="w-8 h-8 {{ $card['icon_class'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </div>
            </div>
        </div>
        @if ($hasLink)
            </a>
        @endif
    @endforeach
</div>

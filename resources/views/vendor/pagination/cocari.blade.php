@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Paginação" class="inline-flex items-center gap-2 text-sm">
        @if ($paginator->onFirstPage())
            <span class="inline-flex items-center px-3 py-1.5 rounded-md border border-gray-200 text-gray-400 cursor-not-allowed">Anterior</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="inline-flex items-center px-3 py-1.5 rounded-md border border-gray-200 text-gray-700 hover:bg-gray-50">Anterior</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="inline-flex items-center px-3 py-1.5 text-gray-400">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="inline-flex items-center px-3 py-1.5 rounded-md border border-gray-900 bg-gray-900 text-white">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="inline-flex items-center px-3 py-1.5 rounded-md border border-gray-200 text-gray-700 hover:bg-gray-50">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="inline-flex items-center px-3 py-1.5 rounded-md border border-gray-200 text-gray-700 hover:bg-gray-50">Próxima</a>
        @else
            <span class="inline-flex items-center px-3 py-1.5 rounded-md border border-gray-200 text-gray-400 cursor-not-allowed">Próxima</span>
        @endif
    </nav>
@endif

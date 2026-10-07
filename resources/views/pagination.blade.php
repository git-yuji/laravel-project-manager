@if ($paginator->hasPages())
    <nav aria-label="ページ移動">
        <ul>
            @if ($paginator->previousPageUrl())
                <li>
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev">← 前へ</a>
                </li>
            @endif

            <li>{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</li>

            @if ($paginator->nextPageUrl())
                <li>
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next">次へ →</a>
                </li>
            @endif
        </ul>
    </nav>
@endif

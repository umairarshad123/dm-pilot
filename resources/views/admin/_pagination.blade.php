@if ($paginator->hasPages())
    <nav class="row" style="margin-top:12px" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="btn sm muted">&laquo; Prev</span>
        @else
            <a class="btn sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">&laquo; Prev</a>
        @endif
        <span class="muted">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }} ({{ $paginator->total() }} total)</span>
        @if ($paginator->hasMorePages())
            <a class="btn sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Next &raquo;</a>
        @else
            <span class="btn sm muted">Next &raquo;</span>
        @endif
    </nav>
@endif

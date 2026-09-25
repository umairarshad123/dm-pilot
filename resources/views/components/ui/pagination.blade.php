{{--
    Paginator view. Use as a pagination *view name*, not as a tag:
        {{ $conversations->links('components.ui.pagination') }}
    Works with LengthAwarePaginator (shows "1–25 of 300" + page numbers) and simple/cursor paginators (prev/next).
--}}
@if ($paginator->hasPages())
    @php
        $lengthAware = method_exists($paginator, 'total');
        $btn = 'inline-flex h-8 min-w-8 items-center justify-center rounded-lg px-2 text-[13px] font-medium transition-colors';
    @endphp
    <nav class="flex flex-wrap items-center justify-between gap-3" aria-label="Pagination">
        <p class="text-[13px] text-ink-muted">
            @if ($lengthAware)
                Showing <span class="font-medium text-ink">{{ number_format($paginator->firstItem()) }}–{{ number_format($paginator->lastItem()) }}</span>
                of <span class="font-medium text-ink">{{ number_format($paginator->total()) }}</span>
            @else
                Page {{ $paginator->currentPage() }}
            @endif
        </p>
        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="{{ $btn }} text-slate-300" aria-disabled="true"><x-ui.icon name="chevron-left" /><span class="sr-only">Previous</span></span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $btn }} text-slate-600 hover:bg-slate-100"><x-ui.icon name="chevron-left" /><span class="sr-only">Previous</span></a>
            @endif

            @if ($lengthAware && isset($elements))
                <span class="hidden items-center gap-1 sm:flex">
                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <span class="{{ $btn }} text-slate-400">…</span>
                        @endif
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span class="{{ $btn }} bg-brand-50 text-brand-700 ring-1 ring-brand-100 ring-inset" aria-current="page">{{ $page }}</span>
                                @else
                                    <a href="{{ $url }}" class="{{ $btn }} text-slate-600 hover:bg-slate-100">{{ $page }}</a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach
                </span>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $btn }} text-slate-600 hover:bg-slate-100"><x-ui.icon name="chevron-right" /><span class="sr-only">Next</span></a>
            @else
                <span class="{{ $btn }} text-slate-300" aria-disabled="true"><x-ui.icon name="chevron-right" /><span class="sr-only">Next</span></span>
            @endif
        </div>
    </nav>
@endif

@if ($paginator->hasPages())
<nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="admin-pager admin-pager-simple">
    @if ($paginator->onFirstPage())
        <span class="admin-pager-btn disabled" aria-disabled="true">{!! __('pagination.previous') !!}</span>
    @else
        <a class="admin-pager-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">{!! __('pagination.previous') !!}</a>
    @endif

    @if ($paginator->hasMorePages())
        <a class="admin-pager-btn" href="{{ $paginator->nextPageUrl() }}" rel="next">{!! __('pagination.next') !!}</a>
    @else
        <span class="admin-pager-btn disabled" aria-disabled="true">{!! __('pagination.next') !!}</span>
    @endif
</nav>
@endif

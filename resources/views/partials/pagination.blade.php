@if ($paginator->hasPages())
<nav class="pager" aria-label="Pages">
  @if (!$paginator->onFirstPage())<a href="{{ $paginator->previousPageUrl() }}">← Previous</a>@endif
  <span class="on">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
  @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}">Next →</a>@endif
</nav>
@endif

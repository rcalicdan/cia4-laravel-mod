@if ($paginator->hasPages())
    <nav>
        <ul class="pagination">
            <!-- Previous Page Link -->
            @if ($paginator->onFirstPage())
                <li class="disabled" aria-disabled="true"><span>@lang('Pager.previous')</span></li>
            @else
                <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev">@lang('Pager.previous')</a></li>
            @endif

            <!-- Next Page Link -->
            @if ($paginator->hasMorePages())
                <li><a href="{{ $paginator->nextPageUrl() }}" rel="next">@lang('Pager.next')</a></li>
            @else
                <li class="disabled" aria-disabled="true"><span>@lang('Pager.next')</span></li>
            @endif
        </ul>
    </nav>
@endif
@if ($paginator->hasPages())
<nav class="pagination-bar" aria-label="صفحات النتائج">
    <p class="pagination-summary">عرض <strong>{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</strong> من <strong>{{ $paginator->total() }}</strong> نتيجة</p>
    <ul class="pagination">
        @if ($paginator->onFirstPage())
            <li class="page-item disabled" aria-disabled="true"><span class="page-link">السابق</span></li>
        @else
            <li class="page-item"><a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">السابق</a></li>
        @endif
        @foreach ($elements as $element)
            @if (is_string($element))
                <li class="page-item pagination-number disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <li class="page-item pagination-number active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                    @else
                        <li class="page-item pagination-number"><a class="page-link" href="{{ $url }}" aria-label="الصفحة {{ $page }}">{{ $page }}</a></li>
                    @endif
                @endforeach
            @endif
        @endforeach
        @if ($paginator->hasMorePages())
            <li class="page-item"><a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">التالي</a></li>
        @else
            <li class="page-item disabled" aria-disabled="true"><span class="page-link">التالي</span></li>
        @endif
    </ul>
</nav>
@endif

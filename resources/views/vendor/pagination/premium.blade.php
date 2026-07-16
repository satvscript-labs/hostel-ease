{{-- The app-wide paginator (registered as the default view in
     AppServiceProvider — every ->links() renders this). Replaces Laravel's
     stock Bootstrap-5 template. Skin lives in _premium.scss under .he-pager.

     Two rules to keep if you touch this:
       1. Every page anchor keeps class="page-link". The fragment router
          (app.js, §4.3) keys off exactly that class to paginate a list
          without reloading the page — rename it and every paginated list in
          the app silently goes back to full reloads.
       2. The hrefs stay real URLs. That's what makes ctrl/middle-click open a
          page in a new tab, and what the no-JS fallback navigates to. --}}
@if ($paginator->hasPages())
    <nav class="he-pager" role="navigation" aria-label="{{ __('Pagination Navigation') }}">

        {{-- Range summary. Laravel gives 1-based first/last item counts; on the
             last page $paginator->lastItem() is the real tail, not page*perPage. --}}
        <p class="he-pager__summary">
            {!! __('Showing :first–:last of :total', [
                'first' => '<strong>'.$paginator->firstItem().'</strong>',
                'last' => '<strong>'.$paginator->lastItem().'</strong>',
                'total' => '<strong>'.$paginator->total().'</strong>',
            ]) !!}
        </p>

        <ul class="he-pager__list">
            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <li class="he-pager__item disabled" aria-disabled="true">
                    <span class="page-link" aria-hidden="true"><i class="fa-solid fa-chevron-left"></i></span>
                </li>
            @else
                <li class="he-pager__item">
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('Previous') }}">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>
                </li>
            @endif

            {{-- Numbers --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="he-pager__item disabled" aria-disabled="true"><span class="he-pager__gap">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="he-pager__item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                        @else
                            <li class="he-pager__item">
                                <a class="page-link" href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <li class="he-pager__item">
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('Next') }}">
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </li>
            @else
                <li class="he-pager__item disabled" aria-disabled="true">
                    <span class="page-link" aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>
                </li>
            @endif
        </ul>
    </nav>
@endif

@if($paginator->hasPages())
<div class="pager">
  <a href="{{ $paginator->previousPageUrl() }}" class="{{ $paginator->onFirstPage() ? 'off' : '' }}">&larr;</a>
  @php $last=$paginator->lastPage(); $cur=$paginator->currentPage(); @endphp
  @for($p=max(1,$cur-2); $p<=min($last,$cur+2); $p++)
    @if($p==$cur)<span class="current">{{ $p }}</span>@else<a href="{{ $paginator->url($p) }}">{{ $p }}</a>@endif
  @endfor
  <a href="{{ $paginator->nextPageUrl() }}" class="{{ $paginator->hasMorePages() ? '' : 'off' }}">&rarr;</a>
</div>
@endif

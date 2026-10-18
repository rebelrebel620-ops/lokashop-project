@if($paginator->hasPages())
@once
<style>
.loka-pagination{display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between;margin:22px 0;font:14px/1.4 "Segoe UI",Arial,sans-serif}.loka-pagination .page-buttons{display:flex;flex-wrap:wrap;gap:6px;align-items:center}.loka-pagination a,.loka-pagination .page-item{display:inline-flex;align-items:center;justify-content:center;min-width:38px;min-height:40px;padding:8px 12px;border:1px solid #d7e3d9;border-radius:8px;background:#fff;color:#175c34;text-decoration:none;box-sizing:border-box}.loka-pagination .selected{background:#146b38;color:#fff;border-color:#146b38;font-weight:700}.loka-pagination .disabled{color:#849087;background:#f3f6f2}.loka-pagination .page-summary{color:#647366;font-size:13px}.loka-pagination a:focus-visible{outline:3px solid #8dbb99;outline-offset:2px}.loka-pagination a:hover{border-color:#146b38;background:#edf6eb}@media(max-width:600px){.loka-pagination{justify-content:center}.loka-pagination .page-summary{width:100%;text-align:center}}
</style>
@endonce
<nav class="loka-pagination" aria-label="Pagination">
 <span class="page-summary">Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}</span>
 <div class="page-buttons">
 @if($paginator->onFirstPage())<span class="page-item disabled" aria-disabled="true">Previous</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>@endif
 @php $current=$paginator->currentPage();$last=$paginator->lastPage();$pages=array_values(array_unique(array_merge([1],range(max(1,$current-2),min($last,$current+2)),[$last])));sort($pages);$previous=0; @endphp
 @foreach($pages as $page)
  @if($previous && $page>$previous+1)<span aria-hidden="true">…</span>@endif
  @if($page==$current)<span class="page-item selected" aria-current="page">{{ $page }}</span>@else<a href="{{ $paginator->url($page) }}" aria-label="Page {{ $page }}">{{ $page }}</a>@endif
  @php $previous=$page; @endphp
 @endforeach
 @if($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>@else<span class="page-item disabled" aria-disabled="true">Next</span>@endif
 </div>
</nav>
@endif

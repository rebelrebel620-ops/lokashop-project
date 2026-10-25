@extends('app')
@section('title','Products - Admin')
@section('content')
<style>
.review-tabs{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px}.review-tabs a{padding:12px 20px;border:1px solid #cddfd4;border-radius:12px;background:white;color:#145532;font-weight:700;text-decoration:none}.review-tabs a[aria-current="page"]{background:#145532;color:white}.review-filter{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;align-items:end;margin-bottom:24px}.review-filter label{display:flex;flex-direction:column;gap:6px}.review-filter input,.review-filter select{width:100%;min-height:44px;box-sizing:border-box}.review-actions{display:flex;gap:10px;flex-wrap:wrap}.review-results{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr));gap:18px}.review-results .section-card{margin:0;overflow-wrap:anywhere}.review-results .shead{gap:12px;flex-wrap:wrap}
</style>
<div class="page-head"><div><h1>Products</h1><p>Browse approved products or review products awaiting compliance approval.</p></div></div>
<nav class="review-tabs" aria-label="Product review views">
<a href="{{url('/admin/products').'?'.http_build_query(array_merge(request()->except(['page','visibility']),['tab'=>'approved']))}}" @if($tab==='approved') aria-current="page" @endif>Approved products</a>
<a href="{{url('/admin/products').'?'.http_build_query(array_merge(request()->except(['page','visibility']),['tab'=>'pending']))}}" @if($tab==='pending') aria-current="page" @endif>Awaiting review</a>
</nav>
<form method="get" action="/admin/products" class="section-card review-filter">
<input type="hidden" name="tab" value="{{$tab}}">
<label>Search products or sellers<input name="q" maxlength="150" value="{{$filters['q']??''}}" placeholder="Product, seller name or email"></label>
<label>Main category<select name="category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{$category}}" @selected(($filters['category']??'')===$category)>{{$category}}</option>@endforeach</select></label>
<label>Stock<select name="stock"><option value="">All stock levels</option><option value="available" @selected(($filters['stock']??'')==='available')>In stock</option><option value="empty" @selected(($filters['stock']??'')==='empty')>Out of stock</option></select></label>
@if($tab==='approved')<label>Visibility<select name="visibility"><option value="">All approved products</option><option value="active" @selected(($filters['visibility']??'')==='active')>Active</option><option value="inactive" @selected(($filters['visibility']??'')==='inactive')>Inactive</option></select></label>@endif
<div class="review-actions"><button class="btn">Apply filters</button><a class="btn alt" href="/admin/products?tab={{$tab}}">Reset</a></div>
</form>
<p>{{$products->total()}} matching {{ $tab==='approved'?'approved products':'products awaiting review' }}</p>
<div class="review-results">
@forelse($products as $p)
<article class="section-card">
<div class="shead"><h3>{{$p->name}}</h3><span class="badge {{$tab==='approved'?'green':'orange'}}">{{$tab==='approved'?'Approved':'Awaiting review'}}</span></div>
<p>{{$p->description}}</p>
<p><strong>{{$p->seller_name??'Seller #'.$p->seller_id}}</strong><br><small>{{$p->seller_email}}</small></p>
<p><small>{{$p->main_category??'Uncategorized'}} · {{$p->subcategory}}</small></p>
<p>₱{{number_format($p->price,2)}} · {{$p->stock}} in stock</p>
@if($tab==='pending')
<form method="post" action="/admin/products/{{$p->id}}" class="review-actions">@csrf<button name="decision" value="approve" class="btn">Approve product</button><button name="decision" value="reject" class="btn danger">Reject product</button></form>
@else<p><small>{{$p->active?'Active listing':'Inactive listing'}}</small></p>@endif
</article>
@empty<div class="section-card empty">No products match this view and its filters.</div>@endforelse
</div>
{{$products->links('components.pagination')}}
@endsection

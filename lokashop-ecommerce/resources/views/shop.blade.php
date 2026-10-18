@extends(auth()->check() && auth()->user()->role === 'buyer' ? 'app' : 'layout')
@php
 $links = auth()->check() && auth()->user()->role === 'buyer' ? app(\App\Http\Controllers\CommonController::class)->links() : [];
@endphp
@section('title','Shop - LokaShop')
@section('body-class','shop-page')
@section('content')
<link rel="stylesheet" href="{{ asset('css/shop.css') }}">
<div class="shop-catalog">
<div class="page-head shop-heading"><div><span class="shop-eyebrow">THE LOCAL EDIT</span><h1>Shop local with LokaShop</h1><p>Browse approved products from verified local sellers.</p></div></div>

@if(request('main_category'))<p><b>{{request('main_category')}}</b> · Browse its subcategories below. <a href="/shop">View all categories</a></p>@endif
<div class="card shop-filters">
 <form method="get" action="/shop" class="catalog-filter-form" style="align-items:end">
  <label>Search products<input name="q" placeholder="What are you looking for?" value="{{request('q')}}"></label>
  <label>Main category<select id="shop-main-category" name="main_category"><option value="">All main categories</option>@foreach($mainCategories as $main)<option value="{{$main}}" @selected(request('main_category')===$main)>{{$main}}</option>@endforeach</select></label>
  @if(request('main_category'))<label>Subcategory<select name="category" id="shop-subcategory"><option value="">All {{request('main_category')}} products</option>@foreach($categories as $c)<option value="{{$c->id}}" @selected(request('category')==$c->id)>{{preg_replace('/^\d+\.\d+\s+/','',$c->name)}}</option>@endforeach</select></label>@endif
  <div><button style="width:100%">Search</button></div>
 </form>
</div>

<div class="grid shop-product-grid">
 @forelse($products as $p)
 <article class="card shop-product-card">
  <div class="shop-product-image">
  @include('product-photo',['photoProduct'=>$p])
  </div>
  <div class="shop-product-details">
  <span class="tag">{{$p->category}}</span>
  @if($p->discount_percent>0)<span class="tag off">{{rtrim(rtrim(number_format($p->discount_percent,1),'0'),'.')}}% OFF</span>@endif
  <h3>{{$p->name}}</h3>
  <p class="price">₱{{number_format($p->price*(1-$p->discount_percent/100),2)}}@if($p->discount_percent>0)<span class="price strike">₱{{number_format($p->price,2)}}</span>@endif</p>
  <small>{{$p->stock}} in stock</small><br>
  <a class="btn" href="{{ url('/product/'.$p->id).'?'.http_build_query(['shop_query'=>request()->getQueryString() ?? '']) }}">View product</a>
  </div>
 </article>
 @empty
 <div class="shop-empty"><h2>No products found</h2><p>Try another search or browse all our local finds.</p><a class="btn" href="/shop">Clear filters</a></div>
 @endforelse
</div>
<div style="margin-top:20px">{{$products->links('components.pagination')}}</div>
</div>
@endsection

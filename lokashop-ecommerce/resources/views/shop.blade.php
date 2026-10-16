@extends(auth()->check() && auth()->user()->role === 'buyer' ? 'app' : 'layout')
@php
 $links = auth()->check() && auth()->user()->role === 'buyer' ? app(\App\Http\Controllers\CommonController::class)->links() : [];
@endphp
@section('title','Shop - LokaShop')
@section('content')
<div class="page-head"><div><h1>Shop local with LokaShop</h1><p>Browse approved products from verified local sellers.</p></div></div>

@if(request('main_category'))<p><b>{{request('main_category')}}</b> · Browse its subcategories below. <a href="/shop">View all categories</a></p>@endif
<div class="card" style="margin-bottom:22px">
 <form method="get" action="/shop" class="form-row" style="align-items:end">
  @if(request('main_category'))<input type="hidden" name="main_category" value="{{request('main_category')}}">@endif
  <label>Search<input name="q" placeholder="Search products" value="{{request('q')}}"></label>
  <label>Subcategory<select name="category"><option value="">All categories</option>@foreach($categories as $c)<option value="{{$c->id}}" @selected(request('category')==$c->id)>{{$c->name}}</option>@endforeach</select></label>
  <div><button style="width:100%">Search</button></div>
 </form>
</div>

<div class="grid">
 @forelse($products as $p)
 <div class="card">
  @include('product-photo',['photoProduct'=>$p])
  <span class="tag">{{$p->category}}</span>
  @if($p->discount_percent>0)<span class="tag off">{{rtrim(rtrim(number_format($p->discount_percent,1),'0'),'.')}}% OFF</span>@endif
  <h3>{{$p->name}}</h3>
  <p class="price">₱{{number_format($p->price*(1-$p->discount_percent/100),2)}}@if($p->discount_percent>0)<span class="price strike">₱{{number_format($p->price,2)}}</span>@endif</p>
  <small>{{$p->stock}} in stock</small><br>
  <a class="btn" href="/product/{{$p->id}}">View product</a>
 </div>
 @empty
 <p>No products match your search.</p>
 @endforelse
</div>
<div style="margin-top:20px">{{$products->links()}}</div>
@endsection

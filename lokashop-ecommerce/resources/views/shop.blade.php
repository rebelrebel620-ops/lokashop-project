@extends('layout')
@section('title','Shop - LokaShop')
@section('content')
<div class="page-head"><div><h1>Shop local with LokaShop</h1><p>Browse approved products from verified local sellers.</p></div></div>

<div class="card" style="margin-bottom:22px">
 <form method="get" action="/shop" class="form-row" style="align-items:end">
  <label>Search<input name="q" placeholder="Search products" value="{{request('q')}}"></label>
  <label>Category<select name="category"><option value="">All categories</option>@foreach($categories as $c)<option value="{{$c->id}}" @selected(request('category')==$c->id)>{{$c->name}}</option>@endforeach</select></label>
  <div><button style="width:100%">Search</button></div>
 </form>
</div>

<div class="grid">
 @forelse($products as $p)
 <div class="card">
  <div class="product" style="--h:{{($p->category_id*47)%360}}">{{mb_strtoupper(mb_substr($p->name,0,1))}}</div>
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

@extends('layout')
@section('title',$p->name.' - LokaShop')
@section('content')
<div class="panel-grid">
 <div>
  <div class="card">
   <div class="product" style="--h:{{($p->category_id*47)%360}}">{{mb_strtoupper(mb_substr($p->name,0,1))}}</div>
  </div>
 </div>
 <div class="card">
  <h1>{{$p->name}}</h1>
  <p>{{$p->description}}</p>
  <p class="price" style="font-size:1.6rem">₱{{number_format($p->price*(1-$p->discount_percent/100),2)}}
   @if($p->discount_percent>0)<span class="price strike">₱{{number_format($p->price,2)}}</span> <span class="tag off">{{rtrim(rtrim(number_format($p->discount_percent,1),'0'),'.')}}% OFF</span>@endif
  </p>
  <small>{{$p->stock}} in stock</small>
  @auth
   @if(auth()->user()->role==='buyer')
   <form method="post" action="/cart" style="margin-top:16px">@csrf
    <input type="hidden" name="product_id" value="{{$p->id}}">
    <label>Variation<select name="variation_id"><option value="">Standard</option>@foreach($variations as $v)<option value="{{$v->id}}">{{$v->name}} ({{$v->stock}} available)</option>@endforeach</select></label>
    <label>Quantity<input type="number" min="1" name="quantity" value="1"></label>
    <button class="btn lg">Add to cart</button>
   </form>
   @endif
  @else
  <a class="btn lg" href="/login" style="margin-top:16px">Log in to order</a>
  @endauth
 </div>
</div>
@endsection

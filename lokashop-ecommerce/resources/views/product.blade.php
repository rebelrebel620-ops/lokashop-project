@extends(auth()->check() && auth()->user()->role === 'buyer' ? 'app' : 'layout')
@php $links=auth()->check()?app(\App\Http\Controllers\CommonController::class)->links():[]; @endphp
@section('title',$p->name.' - LokaShop')
@section('body-class','product-page')
@section('content')
<link rel="stylesheet" href="{{ asset('css/product-detail.css') }}">
<link rel="stylesheet" href="{{asset('css/product-reviews.css')}}">
<div class="product-detail">
<a class="product-back" href="{{ '/shop'.(is_string(request('shop_query')) && request('shop_query') !== '' ? '?'.request('shop_query') : '') }}">&larr; Back to shop</a>
@php
 $productPhotos = json_decode($p->images ?? '[]', true);
 $productPhotos = is_array($productPhotos) ? array_values(array_filter($productPhotos, fn($photo) => is_string($photo) && trim($photo) !== '')) : [];
@endphp
<div class="panel-grid product-detail-grid">
 <div class="card product-gallery">
  @if(count($productPhotos))
   <div class="product-main-photo"><img id="product-main-image" src="{{ \App\Services\Support::imageUrl($productPhotos[0]) }}" alt="{{ $p->name }}"></div>
   @if(count($productPhotos)>1)
   <div class="product-thumbnails" aria-label="Product photos">
    @foreach($productPhotos as $photo)
     <button type="button" class="product-thumbnail" aria-label="View photo {{ $loop->iteration }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" data-gallery-image="{{ \App\Services\Support::imageUrl($photo) }}"><img src="{{ \App\Services\Support::imageUrl($photo) }}" alt="{{ $p->name }} — photo {{ $loop->iteration }}" loading="lazy"></button>
    @endforeach
   </div>
   @endif
  @else
   <div class="product-no-photo"><span aria-hidden="true">▧</span><p>No product photo available</p></div>
  @endif
 </div>
 <div class="card product-summary">
  <span class="product-eyebrow">LOKASHOP MARKETPLACE</span><h1>{{$p->name}}</h1>
<a class="product-rating-link" href="#customer-reviews"><span class="rating-stars" aria-hidden="true">{{str_repeat('★',(int)round($reviewSummary['average']))}}{{str_repeat('☆',5-(int)round($reviewSummary['average']))}}</span> {{$reviewSummary['count']?number_format($reviewSummary['average'],1).' / 5':'No ratings yet'}} · {{$reviewSummary['count']}} reviews <span class="product-sold-count" title="Units from delivered or completed orders, excluding refunded orders">· {{number_format($reviewSummary['sold'])}} sold</span></a>
  <p><strong>Store:</strong> {{$p->store_name ?? 'LokaShop seller'}}<br><strong>Seller:</strong> {{$p->seller_name ?? 'Seller'}}</p>
  <p class="product-intro">Choose your variation and quantity, then add to cart or buy now.</p>
  @php $vacation=\Illuminate\Support\Facades\DB::table('seller_businesses')->where('user_id',$p->seller_id)->value('vacation'); @endphp
  @if($vacation)<p>Store is on vacation. Orders are paused.</p>@endif
  <p class="price" id="live-product-price" data-base-price="{{\App\Services\Catalog::price($p)}}" style="font-size:1.6rem">₱{{number_format(\App\Services\Catalog::price($p),2)}}
   @if($p->discount_percent>0)<span class="price strike">₱{{number_format($p->price,2)}}</span> <span class="tag off">{{rtrim(rtrim(number_format($p->discount_percent,1),'0'),'.')}}% OFF</span>@endif
  </p>
  <div class="product-stock" data-base-stock="{{$p->stock}}">{{$p->stock > 0 ? $p->stock.' units available' : 'Out of stock'}}</div><div class="product-perks"><span>Cash on delivery</span><span>Delivery updates</span></div><p id="selected-variant"></p><img id="variant-image" style="max-width:180px" hidden alt="Selected variation">
  @foreach(\Illuminate\Support\Facades\DB::table('promotions')->where('product_id',$p->id)->where('active',1)->where('starts_at','<=',now())->where('ends_at','>',now())->get() as $promo)<p>{{ucfirst($promo->type)}} ends {{$promo->ends_at}}</p>@endforeach

  @auth
   @if(auth()->user()->role==='buyer')
   <form method="post" action="/cart" style="margin-top:16px" data-purchase-form data-vacation="{{$vacation?1:0}}">@csrf
    <input type="hidden" name="product_id" value="{{$p->id}}">
    <label>Variation<select name="variation_id"><option value="">Original product</option>@foreach($variations as $v)<option value="{{$v->id}}" @disabled(min($p->stock,$v->stock)<=0) data-price="{{\App\Services\Catalog::price($p,$v)}}" data-stock="{{min($p->stock,$v->stock)}}" data-image="{{\App\Services\Support::imageUrl($v->image)}}">{{$v->name}} · {{collect(json_decode($v->options??'{}',true)?:[])->map(fn($value,$key)=>$key.': '.$value)->implode(' / ')}} · ₱{{number_format(\App\Services\Catalog::price($p,$v),2)}} ({{min($p->stock,$v->stock)}} available)</option>@endforeach</select></label>
    <label>Quantity<div class="quantity-stepper"><button type="button" data-quantity-step="-1" aria-label="Decrease quantity">−</button><input type="number" min="1" max="{{$p->stock}}" name="quantity" value="1" required aria-label="Quantity"><button type="button" data-quantity-step="1" aria-label="Increase quantity">+</button></div></label><div class="purchase-actions">
    <button class="btn lg" @disabled($vacation||$p->stock<=0)>Add to cart</button><button class="btn" formaction="/buy-now" @disabled($vacation||$p->stock<=0)>Buy now</button></div>
   </form>
   @php
   $isSaved=\Illuminate\Support\Facades\DB::table('wishlists')->where('buyer_id',auth()->id())->where('product_id',$p->id)->exists();
   $isFollowing=\Illuminate\Support\Facades\DB::table('store_follows')->where('buyer_id',auth()->id())->where('seller_id',$p->seller_id)->exists();
   @endphp
   <div class="product-secondary"><form method="post" action="/tools/wishlist">@csrf<input type="hidden" name="product_id" value="{{$p->id}}"><input type="hidden" name="enabled" value="{{$isSaved?0:1}}"><button aria-pressed="{{$isSaved?'true':'false'}}">{{$isSaved?'Saved · Remove':'Save to wishlist'}}</button></form>
   <form method="post" action="/tools/follow">@csrf<input type="hidden" name="seller_id" value="{{$p->seller_id}}"><input type="hidden" name="enabled" value="{{$isFollowing?0:1}}"><button aria-pressed="{{$isFollowing?'true':'false'}}">{{$isFollowing?'Following · Unfollow':'Follow store'}}</button></form></div>
   @endif
  @else
  <a class="btn lg" href="/login" style="margin-top:16px">Log in to order</a>
  @endauth
 </div>
</div>
<section class="card product-information"><div><span class="product-eyebrow">KNOW YOUR PRODUCT</span><h2>Product details</h2><p class="product-description">{{$p->description}}</p></div><aside><h3>Sold by {{$p->store_name ?? 'LokaShop seller'}}</h3><p>{{$p->seller_name ?? 'Seller'}}</p><dl><dt>Weight</dt><dd>{{$p->weight}} kg</dd><dt>Availability</dt><dd>{{$p->stock>0?'In stock':'Out of stock'}}</dd><dt>Payment</dt><dd>Cash on delivery</dd></dl></aside></section>
@include('product-reviews')

</div>

<script src="{{asset('js/product-selection.js')}}" defer></script>
@endsection

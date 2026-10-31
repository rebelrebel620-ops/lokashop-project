@extends('app')
@section('title','Wishlist - LokaShop')
@section('content')
<link rel="stylesheet" href="{{asset('css/wishlist.css')}}">
<div class="page-head"><div><span class="saved-eyebrow">YOUR FAVOURITES</span><h1>Wishlist</h1><p>Keep products you love and return to the stores you follow.</p></div><a class="btn alt" href="/shop">Browse products</a></div>
<nav class="saved-tabs" aria-label="Wishlist sections"><a href="/tools/wishlist" @if($tab==='products') aria-current="page" @endif>Saved products <span>{{$counts['products']}}</span></a><a href="/tools/wishlist?tab=stores" @if($tab==='stores') aria-current="page" @endif>Following stores <span>{{$counts['stores']}}</span></a></nav>
@if($tab==='products')
<div class="saved-grid">
@forelse($products as $p)
@php $photos=json_decode($p->images??'[]',true);$photo=is_array($photos)?($photos[0]??null):null;$visible=in_array($p->id,$available);$following=in_array($p->seller_id,$followed); @endphp
<article class="saved-card">
<div class="saved-photo">@if($photo)<img src="{{\App\Services\Support::imageUrl($photo)}}" alt="{{$p->name}}" loading="lazy">@else<span>No product photo</span>@endif<span class="saved-chip">{{$visible?($p->stock>0?'Saved':'Out of stock'):'Unavailable'}}</span></div>
<div class="saved-body"><small class="saved-store">{{$p->store_name ?: 'LokaShop seller'}}</small><h2>{{$p->name}}</h2>
@if($visible)<p class="saved-price">₱{{number_format(\App\Services\Catalog::price($p),2)}}</p><p class="saved-stock">{{$p->stock>0?$p->stock.' available':'Currently out of stock'}}{{$p->store_vacation?' · Store on vacation':''}}</p>@else<p class="saved-stock">This listing is currently unavailable. You can keep it saved or remove it.</p>@endif
<div class="saved-actions">@if($visible)<a class="btn" href="/product/{{$p->id}}">View product</a>@endif<form method="post" action="/tools/wishlist">@csrf<input type="hidden" name="product_id" value="{{$p->id}}"><input type="hidden" name="enabled" value="0"><button class="btn alt" aria-label="Remove {{$p->name}} from wishlist">Remove</button></form></div>
@if($following||$visible)<form class="saved-follow" method="post" action="/tools/follow">@csrf<input type="hidden" name="seller_id" value="{{$p->seller_id}}"><input type="hidden" name="enabled" value="{{$following?0:1}}"><button type="submit" aria-pressed="{{$following?'true':'false'}}">{{$following?'Following store · Unfollow':'Follow this store'}}</button></form>@endif
</div>
</article>
@empty<section class="saved-empty"><h2>Your wishlist is ready for favourites</h2><p>Open a product and choose “Save to wishlist” to see it here.</p><a class="btn" href="/shop">Find products</a></section>@endforelse
</div>
{{$products->links('components.pagination')}}
@else
<div class="saved-store-grid">
@forelse($stores as $s)
@php $open=$s->seller_active&&$s->approval_status==='approved'&&$s->account_status==='active'; @endphp
<article class="followed-store"><div class="followed-store-heading">@if($s->logo)<img src="{{\App\Services\Support::imageUrl($s->logo)}}" alt="{{$s->name}} logo" loading="lazy">@else<div class="store-monogram" aria-hidden="true">{{mb_strtoupper(mb_substr($s->name,0,1))}}</div>@endif<div><small class="saved-store">FOLLOWING STORE</small><h2>{{$s->name}}</h2><p>{{$open?($s->vacation?'Store on vacation':'Explore this store’s products'):'Store currently unavailable'}}</p></div></div>@if($s->description)<p class="store-description">{{\Illuminate\Support\Str::limit($s->description,180)}}</p>@endif<div class="saved-actions">@if($open)<a class="btn" href="{{url('/shop').'?'.http_build_query(['seller'=>$s->user_id])}}">Visit store</a>@endif<form method="post" action="/tools/follow">@csrf<input type="hidden" name="seller_id" value="{{$s->user_id}}"><input type="hidden" name="enabled" value="0"><button class="btn alt">Unfollow</button></form></div></article>
@empty<section class="saved-empty"><h2>No followed stores yet</h2><p>Choose “Follow store” on a product page or saved product. Your stores will appear here.</p><a class="btn" href="/shop">Discover stores</a></section>@endforelse
</div>
{{$stores->links('components.pagination')}}
@endif
@endsection

@extends('app')
@section('title','Promotions - LokaShop')
@section('content')
<link rel="stylesheet" href="{{asset('css/seller-business.css')}}"><script src="{{asset('js/seller-promotions.js')}}" defer></script>
<div class="page-head"><div><span class="business-eyebrow">GIVE BUYERS A REASON TO SHOP</span><h1>Promotions</h1><p>Schedule product offers, set clear limits and manage your running campaigns.</p></div><a class="btn alt" href="/seller/products">Manage products</a></div>
<nav class="business-tabs" aria-label="Promotion categories"><a href="/tools/promotions" @if($tab==='offers') aria-current="page" @endif>Sale &amp; flash sale <span>{{$offers->total()}}</span></a><a href="/tools/promotions?tab=deals" @if($tab==='deals') aria-current="page" @endif>Bundles &amp; gifts <span>{{$deals->total()}}</span></a></nav>
@php $ready=$products->filter(fn($p)=>$p->active&&$p->approved&&!$p->draft);$productMap=$products->keyBy('id'); @endphp
@if($ready->isEmpty())
<p class="business-alert">You need an approved, active product before creating a promotion. Add or update your listings in Products.</p>
@endif
@if($tab==='offers')
<section class="business-panel"><h2>Create a sale or flash sale</h2><p class="business-help">A sale sets a lower price for a period. A flash sale also limits the number of discounted units. When both run, the flash price applies to its remaining quota.</p>
<form method="post" action="/tools/promotion" data-offer-form class="promotion-form">@csrf<input type="hidden" name="_request_key" value="{{(string)\Illuminate\Support\Str::uuid()}}">
<div class="business-field-grid"><label>Product<select name="product_id" required data-offer-product><option value="">Choose a product</option>
@foreach($ready as $p)
@php $images=json_decode($p->images??'[]',true); @endphp
<option value="{{$p->id}}" data-price="{{round($p->price*(1-$p->discount_percent/100),2)}}" data-stock="{{$p->stock}}" data-photo="{{\App\Services\Support::imageUrl(is_array($images)?($images[0]??null):null)}}" @selected((string)old('product_id')===(string)$p->id)>{{$p->name}}</option>
@endforeach
</select></label><label>Offer type<select name="type" data-offer-type><option value="sale" @selected(old('type')==='sale')>Sale · scheduled lower price</option><option value="flash" @selected(old('type')==='flash')>Flash sale · limited discounted units</option></select></label><label>Offer price (₱)<input type="number" name="price" min="1" step="0.01" required value="{{old('price')}}" placeholder="Price for one unit"></label><label data-flash-field>Discounted unit limit<input type="number" name="quantity" min="1" step="1" value="{{old('quantity')}}"><small>Required only for a flash sale. Must not exceed current product stock.</small></label><label>Starts<input type="datetime-local" name="starts_at" step="1" value="{{old('starts_at',now()->format('Y-m-d\TH:i:s'))}}" required></label><label>Ends<input type="datetime-local" name="ends_at" step="1" value="{{old('ends_at',now()->addWeek()->format('Y-m-d\TH:i:s'))}}" required></label></div>
<div class="offer-preview"><img data-offer-photo hidden alt="Selected product"><p data-offer-summary aria-live="polite">Choose a product to see its current price, stock and your offer savings.</p></div><div class="business-actions"><button class="btn" @disabled($ready->isEmpty())>Schedule offer</button></div></form></section>
<h2 class="business-section-title">Your product offers</h2><div class="promotion-list">
@forelse($offers as $offer)
@php $state=!$offer->active||now()->gte($offer->ends_at)?'Ended':(now()->lt($offer->starts_at)?'Scheduled':($offer->type==='flash'&&$offer->used>=$offer->quantity?'Sold out':'Live'));$photos=json_decode($offer->images??'[]',true);$photo=is_array($photos)?($photos[0]??null):null; @endphp
<article class="business-panel promotion-record"><div class="business-product">
@if($photo)
<img src="{{\App\Services\Support::imageUrl($photo)}}" alt="{{$offer->product_name}}" loading="lazy">
@endif
<div><small class="business-eyebrow">{{$offer->type==='flash'?'FLASH SALE':'SALE'}}</small><h3>{{$offer->product_name??'Unavailable product'}}</h3><p>₱{{number_format($offer->price,2)}} per unit</p></div><span class="business-status {{strtolower($state)==='live'?'live':($state==='Scheduled'?'scheduled':'ended')}}">{{$state}}</span></div><p class="promotion-schedule">{{\Illuminate\Support\Carbon::parse($offer->starts_at)->format('M j, Y g:i A')}} → {{\Illuminate\Support\Carbon::parse($offer->ends_at)->format('M j, Y g:i A')}}</p>
@if($offer->type==='flash')
<p class="business-help">{{$offer->used}} / {{$offer->quantity}} discounted units used · {{max(0,$offer->quantity-$offer->used)}} remaining</p>
@endif
@if($offer->active&&now()->lt($offer->ends_at))
<form method="post" action="/tools/promotion/end">@csrf<input type="hidden" name="id" value="{{$offer->id}}"><button class="btn alt" onclick="return confirm('End this offer now?')">End offer</button></form>
@endif
</article>
@empty
<section class="business-empty"><h2>No product offers yet</h2><p>Create a sale above to start promoting an approved product.</p></section>
@endforelse
</div>{{$offers->links('components.pagination')}}
@else
<section class="business-panel"><h2>Create a bundle or gift offer</h2><p class="business-help">Bundle discount: buyers reach the required quantity across selected products. Buy X Get Y: buyers must add both qualifying products and the gift product to their cart. The system applies the best qualifying deal from your store.</p>
<form method="post" action="/tools/deal" data-deal-form class="promotion-form">@csrf<input type="hidden" name="_request_key" value="{{(string)\Illuminate\Support\Str::uuid()}}">
<div class="business-field-grid"><label>Deal type<select name="type" data-deal-type><option value="bundle" @selected(old('type')==='bundle')>Bundle · percentage off selected products</option><option value="buy_x_get_y" @selected(old('type')==='buy_x_get_y')>Buy X Get Y · selected gift units free</option></select></label><label>Required purchase quantity<input name="minimum_quantity" type="number" min="1" step="1" value="{{old('minimum_quantity',2)}}" required><small>Combined units of the qualifying products below.</small></label><label data-bundle-field>Bundle discount (%)<input type="number" name="percent" min="0.01" max="100" step="0.01" value="{{old('percent',10)}}"></label><label data-gift-field>Gift product<select name="gift_product_id"><option value="">Choose a different product as the gift</option>
@foreach($ready as $p)
<option value="{{$p->id}}" @selected((string)old('gift_product_id')===(string)$p->id)>{{$p->name}} · {{$p->stock}} in stock</option>
@endforeach
</select></label><label data-gift-field>Free gift units<input type="number" name="gift_quantity" min="1" step="1" value="{{old('gift_quantity',1)}}"><small>Maximum free gift units for one qualifying cart, not per line or variation.</small></label></div>
<fieldset class="qualifying-products"><legend>Qualifying products</legend><p class="business-help">Tick one or more products. The gift must be different from these products.</p><div class="product-checklist">
@foreach($ready as $p)
<label><input type="checkbox" name="product_ids[]" value="{{$p->id}}" @checked(in_array((string)$p->id,array_map('strval',(array)old('product_ids',[])),true))><span>{{$p->name}}<small>₱{{number_format($p->price*(1-$p->discount_percent/100),2)}} · {{$p->stock}} in stock</small></span></label>
@endforeach
</div></fieldset><div class="business-field-grid"><label>Starts<input type="datetime-local" name="starts_at" step="1" value="{{old('starts_at',now()->format('Y-m-d\TH:i:s'))}}" required></label><label>Ends<input type="datetime-local" name="ends_at" step="1" value="{{old('ends_at',now()->addWeek()->format('Y-m-d\TH:i:s'))}}" required></label></div><div class="business-actions"><button class="btn" @disabled($ready->isEmpty())>Schedule deal</button></div></form></section>
<h2 class="business-section-title">Your bundle &amp; gift offers</h2><div class="promotion-list">
@forelse($deals as $deal)
@php $state=!$deal->active||now()->gte($deal->ends_at)?'Ended':(now()->lt($deal->starts_at)?'Scheduled':'Live');$ids=json_decode($deal->product_ids,true)?:[]; @endphp
<article class="business-panel promotion-record"><div class="sales-order-heading"><div><small class="business-eyebrow">{{$deal->type==='bundle'?'BUNDLE':'BUY X GET Y'}}</small><h3>{{$deal->type==='bundle'?$deal->percent.'% off selected products':'Buy '.$deal->minimum_quantity.' qualifying units, get '.$deal->gift_quantity.' gift units free'}}</h3></div><span class="business-status {{$state==='Live'?'live':($state==='Scheduled'?'scheduled':'ended')}}">{{$state}}</span></div><p>Qualifying quantity: {{$deal->minimum_quantity}}</p><ul>
@foreach($ids as $id)
<li>{{$productMap->get($id)->name??'Unavailable product #'.$id}}</li>
@endforeach
</ul>
@if($deal->type==='buy_x_get_y')
<p><strong>Gift:</strong> {{$productMap->get($deal->gift_product_id)->name??'Unavailable gift'}} × {{$deal->gift_quantity}}</p>
@endif
<p class="promotion-schedule">{{\Illuminate\Support\Carbon::parse($deal->starts_at)->format('M j, Y g:i A')}} → {{\Illuminate\Support\Carbon::parse($deal->ends_at)->format('M j, Y g:i A')}}</p>
@if($deal->active&&now()->lt($deal->ends_at))
<form method="post" action="/tools/deal/end">@csrf<input type="hidden" name="id" value="{{$deal->id}}"><button class="btn alt" onclick="return confirm('End this deal now?')">End deal</button></form>
@endif
</article>
@empty
<section class="business-empty"><h2>No bundle or gift offers yet</h2><p>Create your first deal using the form above.</p></section>
@endforelse
</div>{{$deals->links('components.pagination')}}
@endif
@endsection

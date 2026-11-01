@extends('app')
@section('title','Orders - Seller Center')
@section('content')
<link rel="stylesheet" href="{{asset('css/seller-orders-workspace.css')}}">
<div class="page-head"><div><small>SELLER WORKSPACE</small><h1>Orders</h1><p>Review purchases, prepare parcels and request pickup.</p></div></div>
<section class="seller-order-workspace">
<nav class="seller-order-tabs" aria-label="Order status">
@foreach(['pending'=>'New Orders','receive'=>'Processing & Delivery','completed'=>'Closed Orders'] as $key=>$label)
<a href="{{url('/seller/orders').'?'.http_build_query(['tab'=>$key,'q'=>$search])}}" @if($tab===$key) aria-current="page" @endif>{{$label}} <span>{{$counts[$key]}}</span></a>
@endforeach
</nav>
<p class="seller-action-hint">{{['pending'=>'Orders waiting for your acceptance.','receive'=>'Accepted orders being prepared, picked up or delivered to buyers.','completed'=>'Delivered and closed orders. Cancelled and refunded orders retain their status.'][$tab]}}</p>
<form method="get" class="seller-order-search"><input type="hidden" name="tab" value="{{$tab}}"><label>Find an order<input name="q" value="{{$search}}" maxlength="120" placeholder="Order number, buyer, product or tracking code"></label><button class="btn">Search</button><a class="btn alt" href="/seller/orders?tab={{$tab}}">Clear</a><small>{{$orders->total()}} matching orders</small></form>
<div class="seller-order-grid">
@forelse($orders as $o)
@php
 $pickup=$pickups->get($o->parcel_id);$pickupOpen=$pickup && in_array($pickup->status,['requested','offered','accepted']);
 $items=$orderItems->get($o->id,collect());
 $next=['PLACED'=>['CONFIRMED','Accept order'],'CONFIRMED'=>['PREPARING','Start packing'],'PREPARING'=>['READY_FOR_PICKUP','Mark ready for pickup']][$o->status]??null;
@endphp
<article class="seller-purchase-card">
<header><div><small>ORDER #{{$o->id}} · {{\Illuminate\Support\Carbon::parse($o->created_at)->format('M j, Y g:i A')}}</small><h2>{{$o->buyer_name??'Buyer'}}</h2></div><span class="order-status {{in_array($o->status,['CANCELLED','REFUNDED'])?'muted':''}}">{{ucfirst(strtolower(str_replace('_',' ',$o->status)))}}</span></header>
<div class="seller-purchase-items">
@foreach($items as $item)
<div class="seller-purchase-item">
@if($item->image)<img src="{{\App\Services\Support::imageUrl($item->image)}}" alt="{{$item->name}}" loading="lazy">@else<div class="item-placeholder">No photo</div>@endif
<div><strong>{{$item->name}}</strong><small>Qty {{$item->quantity}} · ₱{{number_format($item->unit_price,2)}} each</small></div><b>₱{{number_format($item->unit_price*$item->quantity,2)}}</b>
</div>
@endforeach
</div>
<div class="seller-purchase-total"><span>{{$items->sum('quantity')}} units · Cash on delivery</span><div>Order total <strong>₱{{number_format($o->total,2)}}</strong></div></div>
<div class="seller-purchase-tracking"><small>TRACKING NUMBER</small><span>{{$o->tracking_code ?: 'Awaiting tracking code'}}</span></div>
@if($pickup && in_array($o->status,['PREPARING','READY_FOR_PICKUP']))
<div class="seller-pickup-note"><strong>Pickup: {{ucfirst($pickup->status)}}</strong>
@if($pickup->status==='offered')<p>Rider acceptance deadline: {{\App\Services\PickupRequests::deadline($pickup)->format('M j, Y g:i A')}}.</p>
@elseif(in_array($pickup->status,['expired','declined','failed','reassigned']))<p>Pickup was unsuccessful. Request another pickup below.</p>
@endif</div>
@endif
<footer class="seller-order-actions">
<a class="btn alt" href="/seller/orders/{{$o->id}}/label" target="_blank" rel="noopener">Print label</a>
@if($next && ($next[0]!=='READY_FOR_PICKUP'||($pickup&&in_array($pickup->status,['offered','accepted']))))
<form method="post" action="/seller/orders/{{$o->id}}/status">@csrf<input type="hidden" name="status" value="{{$next[0]}}"><button class="btn">{{$next[1]}}</button></form>
@endif
@if(in_array($o->status,['PREPARING','READY_FOR_PICKUP'])&&!$pickupOpen)
<form method="post" action="/seller/orders/{{$o->id}}/pickup">@csrf<button class="btn">{{$pickup?'Request pickup again':'Request pickup'}}</button></form>
@endif
</footer>
@if(in_array($o->status,['PREPARING','READY_FOR_PICKUP']))<p class="seller-action-hint">Your sorting center assigns the pickup rider. Mark ready after a rider is offered or has accepted.</p>@endif
@if(in_array($o->status,['PLACED','CONFIRMED','PREPARING','READY_FOR_PICKUP']))
<details class="seller-cancel-order"><summary>Cancel this order</summary><form method="post" action="/seller/orders/{{$o->id}}/cancel">@csrf<label>Cancellation reason<textarea name="reason" minlength="10" maxlength="300" rows="3" required placeholder="Explain why this order must be cancelled (10–300 characters)"></textarea></label><button class="btn danger" onclick="return confirm('Cancel this order?')">Confirm cancellation</button></form></details>
@endif
</article>
@empty<section class="seller-order-empty"><h2>No orders in this view</h2><p>Try a different status tab or clear your search.</p></section>@endforelse
</div>
{{$orders->links('components.pagination')}}
</section>
@endsection

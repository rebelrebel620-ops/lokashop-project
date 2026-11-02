@extends('app')
@section('title','My Orders - LokaShop')
@section('content')
<link rel="stylesheet" href="{{asset('css/buyer-orders.css')}}">
<div class="page-head"><div><span class="order-eyebrow">YOUR PURCHASES</span><h1>My orders</h1><p>Follow your purchases from seller acceptance to your doorstep.</p></div></div>
<nav class="order-tabs" aria-label="Order categories">
@foreach(['pending'=>'My Orders','receive'=>'To Receive','completed'=>'Completed'] as $key=>$label)
<a href="{{url('/orders').'?'.http_build_query(['tab'=>$key])}}" @if($tab===$key) aria-current="page" @endif>{{$label}} <span>{{$counts[$key]}}</span></a>
@endforeach
</nav>
<p class="order-tab-hint">{{['pending'=>'Orders waiting for the seller to accept.','receive'=>'Accepted orders being prepared, picked up, transported or delivered.','completed'=>'Delivered orders and closed order history. Cancelled and refunded orders keep their own status.'][$tab]}}</p>
<div class="buyer-order-list">
@forelse($orders as $o)
@php $items=$orderItems->get($o->id,collect());$quantity=$items->sum('quantity');$closed=in_array($o->status,['CANCELLED','REFUNDED','RETURN_RECEIVED']); @endphp
<article class="purchase-card">
<header class="purchase-header"><div><strong>{{$o->store_name ?: 'LokaShop seller'}}</strong><small>Order #{{$o->id}} · {{\Illuminate\Support\Carbon::parse($o->created_at)->format('M j, Y')}}</small></div><span class="purchase-status {{$closed?'closed':''}}">{{\App\Services\BuyerOrders::label($o->status)}}</span></header>
@foreach($items->take(1) as $item) @include('order-item-row',['item'=>$item]) @endforeach
@if($items->count()>1)<details class="purchase-more"><summary>View {{$items->count()-1}} more {{ $items->count()-1===1?'product':'products' }}</summary>@foreach($items->skip(1) as $item) @include('order-item-row',['item'=>$item]) @endforeach</details>@endif
<div class="purchase-total"><span>{{$quantity}} {{$quantity===1?'item':'items'}} · Cash on delivery</span><span>Order total <strong>₱{{number_format($o->total,2)}}</strong></span></div>
@if($tab==='receive')
<div class="purchase-update"><div><strong>{{\App\Services\BuyerOrders::label($o->status)}}</strong><small>{{$o->failure_reason ?: 'Tracking code: '.$o->tracking_code}}</small></div></div>
@endif
@if($o->status==='DELIVERED')<p class="receipt-hint">Delivery is recorded. Confirm receipt after checking your items, or visit Returns if there is a problem.</p>@endif
<div class="purchase-actions" role="group" aria-label="Order actions">
@if($tab==='receive')<a class="btn alt" href="/track/{{rawurlencode($o->tracking_code)}}">Track Order</a>@endif
@if(in_array($o->status,['DELIVERED','COMPLETED'])&&$o->proof_path)<a class="btn alt" href="/orders/{{$o->id}}/proof" target="_blank" rel="noopener">Delivery photo</a>@endif
@if($o->status==='DELIVERED')<form method="post" action="/orders/{{$o->id}}/confirm">@csrf<button class="btn">Order received</button></form><a class="btn alt" href="/tools/returns">Returns</a>@endif
@if($o->status==='COMPLETED')<a class="btn" href="/ratings">Review items</a>@endif
@if(in_array($o->status,['PLACED','CONFIRMED','PREPARING']))<details class="cancel-order"><summary>Cancel order</summary><form method="post" action="/orders/{{$o->id}}/cancel">@csrf<label>Cancellation reason<textarea name="reason" minlength="10" maxlength="300" required placeholder="Tell us why (at least 10 characters)"></textarea></label><button class="btn danger">Confirm cancellation</button></form></details>@endif
</div>
</article>
@empty
<section class="purchase-card order-empty"><h2>{{['pending'=>'No orders waiting for acceptance','receive'=>'No orders on the way','completed'=>'No completed orders yet'][$tab]}}</h2><p>Your orders will appear here as they progress.</p><a class="btn" href="/shop">Browse products</a></section>
@endforelse
</div>
{{$orders->links('components.pagination')}}
@endsection

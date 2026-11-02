@extends(auth()->user()->role==='buyer'?'app':'layout')
@section('title','Track Order - LokaShop')
@section('content')
<link rel="stylesheet" href="{{asset('css/buyer-orders.css')}}">
<a class="order-back" href="{{auth()->user()->role==='buyer'?url('/orders').'?'.http_build_query(['tab'=>\App\Services\BuyerOrders::bucket($order->status)]):url('/seller/orders')}}">&larr; Back to orders</a>
<div class="page-head"><div><span class="order-eyebrow">DELIVERY UPDATES</span><h1>Track your order</h1><p>Order #{{$order->id}} · {{$parcel->tracking_code}}</p></div><a class="btn alt" href="{{url('/track/'.rawurlencode($parcel->tracking_code))}}">Refresh updates</a></div>
<div class="tracking-layout">
<section class="purchase-card timeline-card"><div class="tracking-current"><small>CURRENT STATUS</small><h2>{{\App\Services\BuyerOrders::label($order->status)}}</h2><p>Latest order update: {{\Illuminate\Support\Carbon::parse($order->updated_at)->format('M j, Y · g:i A')}}</p></div>
<h2 class="tracking-heading">Delivery history</h2><p class="timeline-hint">Newest update first. Only recorded events have timestamps.</p>
<ol class="delivery-timeline">
@foreach($timeline as $event)
<li @if($loop->first) class="latest" @endif><time datetime="{{\Illuminate\Support\Carbon::parse($event['at'])->toIso8601String()}}"><span>{{\Illuminate\Support\Carbon::parse($event['at'])->format('M j, Y')}}</span><small>{{\Illuminate\Support\Carbon::parse($event['at'])->format('g:i A')}}</small></time><div><h3>{{$event['title']}}</h3>@if($event['detail'])<p>{{$event['detail']}}</p>@endif</div></li>
@endforeach
</ol>
<details class="delivery-process"><summary>View the full delivery process</summary><ol><li>Buyer places the order.</li><li>Seller accepts and prepares the products.</li><li>Seller requests pickup; the sorting center assigns a local rider.</li><li>Rider accepts, collects the parcel and brings it to the origin sorting center.</li><li>Sorting center receives and sorts the parcel.</li><li>For another municipality: a driver accepts the transfer and departs with the parcels.</li><li>Driver confirms handover; the destination sorting center confirms receipt. Transfers repeat if needed.</li><li>Destination sorting center sorts the parcel and assigns a local delivery rider.</li><li>Rider goes out for delivery.</li><li>Buyer receives the parcel and pays COD; rider records delivery proof.</li><li>Buyer confirms receipt, or the existing automatic completion rule applies.</li></ol><p>This describes the route, not completed events. Local deliveries can skip sorting center transfers. Failed deliveries and returns appear in the history when recorded.</p></details>
</section>
<aside class="tracking-sidebar"><section class="purchase-card"><h2>Order summary</h2><p><strong>{{$store->name ?? $seller->name ?? 'LokaShop seller'}}</strong></p>
@foreach($items as $item) @include('order-item-row',['item'=>$item]) @endforeach
<dl class="order-breakdown"><dt>Items subtotal</dt><dd>₱{{number_format($items->sum(fn($i)=>$i->unit_price*$i->quantity),2)}}</dd><dt>Shipping</dt><dd>₱{{number_format($order->shipping_fee??0,2)}}</dd><dt>Store discount</dt><dd>−₱{{number_format($order->store_discount??0,2)}}</dd><dt>Platform discount</dt><dd>−₱{{number_format($order->platform_discount??0,2)}}</dd><dt>Shipping discount</dt><dd>−₱{{number_format($order->shipping_discount??0,2)}}</dd><dt><strong>Order total</strong></dt><dd><strong>₱{{number_format($order->total,2)}}</strong></dd></dl>
@if($payment)<p class="order-payment">{{strtoupper($payment->method)}} · {{ucfirst(str_replace('_',' ',$payment->status))}}</p>@endif
@if($address)<h3>Delivery address</h3><p class="order-address">{{$address->recipient ?? $buyer->name ?? 'Buyer'}}<br>{{$address->phone ?? ''}}<br>{{$address->street ?? ''}}, {{$address->barangay ?? ''}}, {{$address->city ?? ''}}, {{$address->province ?? ''}} {{$address->postal_code ?? ''}}</p>@endif
</section>
@if(in_array($order->status,['DELIVERED','COMPLETED'])&&$parcel->proof_path)<section class="purchase-card"><h2>Proof of delivery</h2><p>The rider's photo covers this order and the items listed above.</p><a href="/orders/{{$order->id}}/proof" target="_blank" rel="noopener"><img class="tracking-proof" src="/orders/{{$order->id}}/proof" alt="Delivery proof for order {{$order->id}}" loading="lazy">Open delivery photo</a></section>@endif
@if(auth()->id()==$order->buyer_id && $order->status==='DELIVERED')<section class="purchase-card"><h2>Received your items?</h2><p>Check the items before confirming receipt.</p><form method="post" action="/orders/{{$order->id}}/confirm">@csrf<button class="btn">Order received</button></form><p><a href="/tools/returns">Report a problem / Returns</a></p></section>@endif
</aside>
</div>
@endsection

@extends('app')
@section('title','My Orders - LokaShop')
@section('content')
<div class="page-head"><div><h1>My orders</h1><p>Track every order from placement to delivery.</p></div></div>

@forelse($orders as $o)
<div class="section-card">
 <div class="shead">
  <div><h3>Order #{{$o->id}}</h3><small>Tracking code {{$o->tracking_code}}</small></div>
  <span class="badge {{ in_array($o->status,['DELIVERED','COMPLETED']) ? 'green' : (in_array($o->status,['DELIVERY_FAILED','RETURNED']) ? 'red' : 'blue') }}">{{ucfirst(strtolower(str_replace('_',' ',$o->status)))}}</span>
 </div>
 <p style="margin:0"><b class="price" style="font-size:1rem">Total: ₱{{number_format($o->total,2)}}</b></p>
 @if($o->failure_reason)<p style="color:var(--red)"><small>Delivery issue: {{$o->failure_reason}}</small></p>@endif
<ul>@foreach(\Illuminate\Support\Facades\DB::table('order_items')->where('order_id',$o->id)->get() as $item)<li>{{$item->name}} × {{$item->quantity}}</li>@endforeach</ul>
 @if(in_array($o->status,['DELIVERED','COMPLETED']))<p><a href="/orders/{{$o->id}}/proof">View delivery proof</a></p>@endif
 @if($o->status==='DELIVERED')
 <form method="post" action="/orders/{{$o->id}}/confirm">@csrf<button class="btn">Confirm received</button></form>
 @endif
 @if($o->status==='COMPLETED')<p><a href="/ratings">Review your purchased items.</a></p>@endif
 <p><a href="/track/{{$o->tracking_code}}">Order details</a> · <a href="/tools/returns">Returns</a></p>
 @if(in_array($o->status,['PLACED','CONFIRMED','PREPARING']))<form method="post" action="/orders/{{$o->id}}/cancel">@csrf<label>Cancellation reason<input name="reason" minlength="10" maxlength="300" required></label><button>Cancel order</button></form>@endif

</div>
@empty
<div class="section-card empty">No orders yet. <a href="/shop">Start shopping &rarr;</a></div>
@endforelse
@endsection

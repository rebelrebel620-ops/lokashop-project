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
 @if($o->status==='DELIVERED')
 <form method="post" action="/orders/{{$o->id}}/confirm">@csrf<button class="btn">Confirm received</button></form>
 @endif
 @if($o->status==='COMPLETED')
 <form method="post" action="/orders/{{$o->id}}/rating" class="inline-form" style="margin-top:10px;display:block">@csrf
  <label>Your rating<select name="stars">@for($i=5;$i>=1;$i--)<option value="{{$i}}">{{$i}} stars</option>@endfor</select></label>
  <label>Feedback<textarea name="comment" placeholder="Tell other buyers about this seller"></textarea></label>
  <button class="btn">Save feedback</button>
 </form>
 @endif
</div>
@empty
<div class="section-card empty">No orders yet. <a href="/shop">Start shopping &rarr;</a></div>
@endforelse
@endsection

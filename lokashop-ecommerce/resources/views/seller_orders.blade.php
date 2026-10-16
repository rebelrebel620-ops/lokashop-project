@extends('app')
@section('title','Orders - Seller Center')
@section('content')
<div class="page-head"><div><h1>Orders</h1><p>Confirm, pack and hand off orders for pickup.</p></div></div>

@forelse($orders as $o)
<div class="section-card">
 <div class="shead">
  <div><h3>Order #{{$o->id}}</h3><small>Parcel #{{$o->parcel_id}} &middot; Total ₱{{number_format($o->total,2)}}</small></div>
  <span class="badge {{ in_array($o->status,['DELIVERED','COMPLETED']) ? 'green' : (in_array($o->status,['PLACED','CONFIRMED','PREPARING']) ? 'orange' : 'blue') }}">{{ucfirst(strtolower(str_replace('_',' ',$o->status)))}}</span>
 </div>
 <ul>@foreach(\Illuminate\Support\Facades\DB::table('order_items')->where('order_id',$o->id)->get() as $item)<li><b>{{$item->name}}</b> × {{$item->quantity}}</li>@endforeach</ul>
 <div class="inline-form">
  <a class="btn sm alt" href="/seller/orders/{{$o->id}}/label" target="_blank">Print shipping label</a>
  @foreach(['PLACED'=>'CONFIRMED','CONFIRMED'=>'PREPARING','PREPARING'=>'READY_FOR_PICKUP'] as $from=>$to)
   @if($o->status===$from)
   <form method="post" action="/seller/orders/{{$o->id}}/status">@csrf<input type="hidden" name="status" value="{{$to}}"><button class="btn sm">Mark as {{ucfirst(strtolower(str_replace('_',' ',$to)))}}</button></form>
   @endif
  @endforeach
  @if($o->status==='PREPARING')
  <form method="post" action="/seller/orders/{{$o->id}}/pickup" class="inline-form">@csrf
   <select name="rider_id">@foreach($riders as $rider)<option value="{{$rider->id}}">{{$rider->name}}</option>@endforeach</select>
   <button class="btn sm alt">Request pickup</button>
  </form>
  @endif
 </div>
 @if(in_array($o->status,['PLACED','CONFIRMED','PREPARING','READY_FOR_PICKUP']))<form method="post" action="/seller/orders/{{$o->id}}/cancel">@csrf<input name="reason" minlength="10" maxlength="300" placeholder="Cancellation reason" required><button>Cancel order</button></form>@endif
</div>
@empty
<div class="section-card empty">No orders yet.</div>
@endforelse
{{$orders->links()}}
@endsection

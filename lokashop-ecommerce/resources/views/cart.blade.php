@extends('app')
@section('title','My Cart - LokaShop')
@section('content')
<div class="page-head"><div><h1>My cart</h1><p>Items from different sellers become separate orders at checkout.</p></div></div>

<div class="panel-grid">
 <div>
  <div class="section-card">
   <div class="shead"><h3>Items</h3></div>
   @forelse($rows as $x)
   <div class="order-row">
    <div class="thumb">{{mb_strtoupper(mb_substr($x->name,0,1))}}</div>
    <div class="grow"><b>{{$x->name}} {{$x->variation}}</b><small>Quantity: {{$x->quantity}}</small></div>
    <div class="meta"><b class="price" style="font-size:1rem">₱{{number_format(($x->price+$x->price_adjustment)*(1-$x->discount_percent/100),2)}}</b><br><small>each</small></div>
   </div>
   @empty
   <div class="empty">Your cart is empty. <a href="/shop">Browse products &rarr;</a></div>
   @endforelse
  </div>
 </div>

 <div>
  <div class="form-card">
   <h3>Delivery address</h3>
   <form method="post" action="/addresses">@csrf
    @foreach(['recipient'=>'Recipient','phone'=>'Phone','street'=>'Street / house number','barangay'=>'Barangay','city'=>'City or municipality','province'=>'Province','postal_code'=>'Postal code'] as $name=>$label)
    <label>{{$label}}<input name="{{$name}}" @if($name!=='postal_code') required @endif></label>
    @endforeach
    <button class="btn" style="width:100%">Save address</button>
   </form>
  </div>

  <div class="form-card">
   <h3>Checkout</h3>
   <form method="post" action="/checkout">@csrf
    <label>Delivery address<select name="address_id" required>
     @forelse($addresses as $a)<option value="{{$a->id}}">{{$a->street}}, {{$a->barangay}}, {{$a->city}}, {{$a->province}}</option>@empty<option value="">Add an address first</option>@endforelse
    </select></label>
    <label>Payment method<select name="payment_method">
     <option value="cod">Cash on delivery</option>
     <option value="manual">Manual payment (awaiting confirmation)</option>
    </select></label>
    <label>Voucher code<input name="voucher" placeholder="Optional"></label>
    <button class="btn lime" style="width:100%">Place order</button>
   </form>
  </div>
 </div>
</div>
@endsection

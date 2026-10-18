@extends('app')
@section('title','My Cart - LokaShop')
@section('content')
<div class="page-head"><div><h1>My cart</h1><p>Each distinct product becomes its own order. Multiple quantities of the same product stay together.</p></div></div>

<div class="panel-grid">
 <div>
  <div class="section-card">
   <div class="shead"><h3>Items</h3></div>
   @forelse($rows as $x)
   <div class="order-row">
    <input type="checkbox" style="width:20px;height:20px;flex:none" name="cart_ids[]" value="{{$x->id}}" form="checkout-form" aria-label="Select {{$x->name}}" @checked(in_array((string)$x->id,array_map('strval',old('cart_ids',request('selected')?[request('selected')]:[]))))>
    @include('product-photo',['photoProduct'=>$x,'photoSize'=>64])
    <div class="grow"><b>{{$x->name}} {{$x->variation}}</b>
     <form id="cart-update-{{$x->id}}" class="cart-quantity" method="post" action="/cart/{{$x->id}}/update">@csrf
      <label for="cart-quantity-{{$x->id}}">Quantity</label><input id="cart-quantity-{{$x->id}}" name="quantity" type="number" min="1" max="99999" value="{{$x->quantity}}" required>
     </form>
     <div class="cart-actions">
      <button class="cart-update" type="submit" form="cart-update-{{$x->id}}">Update</button>
      <form class="cart-remove-form" method="post" action="/cart/{{$x->id}}/remove">@csrf<button class="cart-remove" type="submit">Remove</button></form>
     </div>
    </div>
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
   <form id="checkout-form" method="post" action="/checkout"><input type="hidden" name="selection_required" value="1">@csrf<input type="hidden" name="_request_key" value="{{(string)\Illuminate\Support\Str::uuid()}}">
<p>Tick the products you want to buy. Unselected items stay in your cart.</p>
    <label>Delivery address<select name="address_id" required>
     @forelse($addresses as $a)<option value="{{$a->id}}">{{$a->street}}, {{$a->barangay}}, {{$a->city}}, {{$a->province}}</option>@empty<option value="">Add an address first</option>@endforelse
    </select></label>
    <input type="hidden" name="payment_method" value="cod"><p><b>Payment: Cash on delivery (COD)</b></p>
@php $available=\Illuminate\Support\Facades\DB::table('vouchers')->where('active',1)->where(fn($q)=>$q->whereNull('starts_at')->orWhere('starts_at','<=',now()))->where(fn($q)=>$q->whereNull('expires_at')->orWhere('expires_at','>',now()))->get(); @endphp
@foreach(['platform_voucher'=>'Platform voucher','shipping_voucher'=>'Shipping voucher','voucher'=>'Store voucher'] as $field=>$label)
<label>{{$label}}<select name="{{$field}}"><option value="">No voucher</option>
@foreach($available as $voucher)
@if(($field==='voucher' && $voucher->seller_id && $rows->contains('seller_id',$voucher->seller_id)) || ($field==='platform_voucher' && !$voucher->seller_id && $voucher->type!=='shipping') || ($field==='shipping_voucher' && !$voucher->seller_id && $voucher->type==='shipping'))
<option value="{{$voucher->code}}" @selected(old($field)===$voucher->code)>{{$voucher->code}} · {{$voucher->type==='percent'?$voucher->value.'%':'₱'.number_format($voucher->value,2)}} off · minimum ₱{{number_format($voucher->minimum_spend,2)}}</option>
@endif @endforeach</select></label>@endforeach
<label>Note to seller<textarea name="note" maxlength="200"></textarea></label><p>Vouchers are optional. Shipping uses the active logistics rate for your address, or a flat ₱50 per seller shipment when no active rate matches.</p><button class="btn lime" style="width:100%">Place order</button>
   </form>
  </div>
 </div>
</div>
@endsection

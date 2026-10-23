@extends('dashboard')
@section('title','Courier assignments')
@section('panel')
<link rel="stylesheet" href="{{ asset('css/courier-delivery.css') }}">
<div class="page-head"><div><h1>Courier assignments</h1><p>New assignments appear here automatically. Accept or decline pickup offers within 3 hours.</p></div></div>
<section class="panel-card courier-scan" id="assignment-scanner" hidden>
 <h2>Scan parcel QR</h2><p>Use the label printed by the seller. Scanning identifies your assignment; it does not mark a parcel delivered.</p>
 <div class="courier-buttons"><button type="button" id="start-scan">Open camera scanner</button><button type="button" id="stop-scan" hidden>Stop camera</button><label class="btn" for="qr-photo">Scan QR from photo</label><input id="qr-photo" type="file" accept="image/*" hidden></div>
 <video id="qr-video" muted autoplay playsinline hidden></video><canvas id="qr-canvas" hidden></canvas>
 <p id="scan-message" role="status" aria-live="polite"></p>
 <form id="parcel-lookup" method="post" action="/rider/scan">@csrf<label>QR contents or printed tracking code<input id="scan-code" name="code" maxlength="2000" placeholder="LKS-… or tracking link" required></label><button>Find my parcel</button></form>
</section>
@forelse($assignments as $a)
@php
 $snapshot=json_decode($a->address_snapshot??'null');
 $matched=(int)session('scanned_parcel')===(int)$a->parcel_id;
 $scanValue=$matched?session('scanned_code'):'';
 $recipient=$snapshot?:$a;
@endphp
<article class="panel-card courier-job" id="assignment-{{$a->id}}" data-parcel="{{$a->parcel_id}}">
 <div class="page-head"><div><h2>{{ucfirst($a->kind)}} · {{$a->tracking_code}}</h2><p>Order #{{$a->order_id}} · Assignment {{ucfirst($a->status)}}</p></div><x-badge :status="$a->parcel_status" /></div>
 <p>{{$a->city}}, {{$a->province}}</p>
 @if($matched)<p class="notice">Parcel label matched. Check the details below.</p>@endif
 @if($a->kind==='pickup' && $a->status==='offered')<p>Accept before: {{\App\Services\PickupRequests::deadline($a)->format('M j, Y g:i A')}} (3 hours from center assignment).</p>@endif
 @if($a->kind==='delivery' && $a->status==='offered')<p>Accept before {{\App\Services\DeliveryOffers::deadline($a)->format('M j, Y g:i A')}} (12-hour delivery offer).</p>@endif
 @if($a->status==='expired')<p class="notice">{{ $a->kind==='pickup' ? 'Pickup request expired. The seller can request another rider.' : 'This offer expired. Ask your sorting center to resend the delivery or transfer offer.' }}</p>@endif
 @if($a->status==='offered')
 <form method="post" action="/rider/assignments/{{$a->id}}/accept">@csrf<button>Accept assignment</button></form>
 <form method="post" action="/ops/decline">@csrf<input type="hidden" name="id" value="{{$a->id}}"><button class="danger">Decline assignment</button></form>
 @endif
 @if($a->status==='accepted')
  @if(($a->kind==='pickup' && $a->parcel_status==='READY_FOR_PICKUP') || ($a->kind==='delivery' && $a->parcel_status==='OUT_FOR_DELIVERY'))<button type="button" onclick="document.getElementById('assignment-scanner').hidden=false;document.getElementById('assignment-scanner').scrollIntoView({behavior:'smooth'})">Scan this parcel label</button>@endif
  @if($a->kind==='pickup')
   @php $pickupAddress=\App\Services\Municipalities::address(\Illuminate\Support\Facades\DB::table('orders')->where('id',$a->order_id)->value('seller_id')); @endphp
   <p>Seller pickup: {{$pickupAddress->street ?? ''}}, {{$pickupAddress->barangay ?? ''}}, {{$pickupAddress->city ?? ''}}</p>
  @endif
  @if($a->kind==='pickup' && $a->parcel_status==='PICKED_UP')<p>Parcel collected. Return it to your sorting center, then complete pickup.</p><form method="post" action="/rider/parcels/{{$a->parcel_id}}/status">@csrf<button name="status" value="AT_SORTING_CENTER">Complete pickup — returned to center</button></form>@endif
  @if($a->kind==='transfer' && $a->parcel_status==='TRANSFER_OFFERED')<form method="post" action="/rider/transfers/{{$a->parcel_id}}/depart">@csrf<button>Depart for destination sorting center</button></form>@endif
  @if($a->kind==='transfer' && $a->parcel_status==='IN_TRANSIT_CENTER')<p>Drive to the destination sorting center. Its staff must confirm receipt.</p>@endif
  @if($a->kind==='pickup' && $a->parcel_status==='READY_FOR_PICKUP')
  <form method="post" action="/rider/parcels/{{$a->parcel_id}}/status">@csrf<input type="hidden" name="status" value="PICKED_UP"><label>Scanned label or printed tracking code<input name="scanned_code" value="{{$scanValue}}" required maxlength="2000" placeholder="Scan above, or type the label code"></label><button>Confirm pickup from seller</button></form>
  @endif
  @if($a->kind==='delivery')
   <p><strong>Recipient:</strong> {{$recipient->recipient}} · {{$recipient->phone}}</p>
   <p>{{$recipient->street}}, {{$recipient->barangay}}, {{$recipient->city}}, {{$recipient->province}}</p>
   <p><strong>Order total / COD: ₱{{number_format($a->cod_total,2)}}</strong></p>
   @if($a->parcel_status==='ASSIGNED_TO_RIDER')<form method="post" action="/rider/parcels/{{$a->parcel_id}}/status">@csrf<button name="status" value="OUT_FOR_DELIVERY">Out for delivery</button></form>@endif
   @if($a->parcel_status==='OUT_FOR_DELIVERY')
   <form class="delivery-form" method="post" enctype="multipart/form-data" action="/rider/parcels/{{$a->parcel_id}}/status">@csrf
    <input type="hidden" name="status" value="DELIVERED">
    <label>Scanned label or printed tracking code<input name="scanned_code" value="{{$scanValue}}" maxlength="2000" required placeholder="Scan above, or type the label code"></label>
    <label>Take or upload delivery photo<input class="proof-input" name="proof" type="file" accept="image/jpeg,image/png" capture="environment" required></label>
    <small>On a phone, take a photo using the rear camera. JPG or PNG, maximum 5 MB. The buyer can view this photo after delivery.</small>
    <img class="proof-preview" hidden alt="Preview of your delivery proof"><p class="proof-message" role="status"></p>
    <div class="delivery-fields"><label>Buyer's six-digit delivery OTP<input name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" minlength="6" autocomplete="off" required></label><label>Exact COD collected (₱)<input name="cod_amount" type="number" step="0.01" min="0" placeholder="{{number_format($a->cod_total,2,'.','')}}" required></label></div>
    <button>Submit proof and mark delivered</button>
   </form>
   <section class="delivery-failure"><h3>Unable to deliver?</h3><form method="post" action="/rider/parcels/{{$a->parcel_id}}/status">@csrf<input type="hidden" name="status" value="DELIVERY_FAILED"><label>Failure reason<select name="reason" required><option value="">Choose reason</option><option>Buyer not home</option><option>Wrong address</option><option>Refused</option></select></label><button class="danger">Report delivery failure</button></form></section>
   @endif
  @endif
 @endif
 @if($a->status==='completed' && $a->kind==='pickup')<p class="notice">Pickup complete. Parcel returned to sorting center.</p>@endif
 @if($a->status==='completed' && $a->kind==='delivery')<p class="notice">Delivery recorded. The buyer can now view the proof on My orders.</p>@endif
</article>
@empty<div class="panel-card empty">No assignments yet. Go Online and ask your sorting center to assign a parcel.</div>@endforelse
{{ $assignments->links('components.pagination') }}
<script src="{{asset('js/vendor/jsQR.js')}}" defer></script>
<script src="{{asset('js/courier-delivery.js')}}" defer></script>
<script>
let assignmentDirty=false;document.addEventListener('input',()=>assignmentDirty=true);
setInterval(()=>{if(!document.hidden&&!assignmentDirty&&document.getElementById('assignment-scanner').hidden)location.reload();},20000);
</script>
@endsection

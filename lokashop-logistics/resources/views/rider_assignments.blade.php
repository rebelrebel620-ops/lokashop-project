@extends('dashboard')
@section('title','Rider portal')
@section('panel')
@php
  $items = $assignments->getCollection();
  $pickupsAssigned = $items->where('kind','pickup')->whereIn('status',['offered','accepted'])->count();
  $deliveriesToday = $items->where('kind','delivery')->whereIn('status',['offered','accepted'])->count();
  $completed = $items->where('status','completed')->count();
@endphp

<div class="page-head">
  <div><h1>Good day, {{ auth()->user()->name }}</h1><p>Your pickups and deliveries, all in one place.</p></div>
</div>

<div class="stat-grid">
  <div class="stat-card"><span class="ico c-blue"><x-icon name="box"/></span><div><div class="num">{{ $pickupsAssigned }}</div><div class="lbl">Pickups assigned</div></div></div>
  <div class="stat-card"><span class="ico c-teal"><x-icon name="truck"/></span><div><div class="num">{{ $deliveriesToday }}</div><div class="lbl">Deliveries in progress</div></div></div>
  <div class="stat-card"><span class="ico c-green"><x-icon name="check-circle"/></span><div><div class="num">{{ $completed }}</div><div class="lbl">Completed</div></div></div>
</div>

<div class="panel-card">
  <div class="p-head"><h2>My assignments</h2></div>
  <div class="p-body flush">
    <div class="row-list">
      @forelse($assignments as $a)
        <div class="row-item">
          <span class="ico"><x-icon name="{{ $a->kind==='pickup' ? 'box' : 'truck' }}"/></span>
          <div class="info">
            <b>{{ ucfirst($a->kind) }} &middot; Parcel #{{ $a->parcel_id }}</b>
            <small>{{ $a->city }}, {{ $a->province }}</small>
          </div>
          <x-badge :status="$a->parcel_status" />
          <div class="actions">
            @if($a->status==='offered')
              <form class="inline" method="post" action="/rider/assignments/{{ $a->id }}/accept">@csrf
                <button class="sm"><x-icon name="check" size="15"/> Accept assignment</button>
              </form>
            @endif
            @if($a->status==='accepted')
@if($a->kind==='transfer' && $a->parcel_status==='TRANSFER_OFFERED')<form method="post" action="/rider/transfers/{{$a->parcel_id}}/depart">@csrf<button class="btn">Depart for destination sorting center</button></form>@endif
@if($a->kind==='transfer' && $a->parcel_status==='IN_TRANSIT_CENTER')<p>Drive to the destination sorting center. Its staff must confirm receipt.</p>@endif
              @if($a->kind==='pickup' && $a->parcel_status==='READY_FOR_PICKUP')
                <form class="inline" method="post" enctype="multipart/form-data" action="/rider/parcels/{{ $a->parcel_id }}/status">@csrf
                  <button class="sm" name="status" value="PICKED_UP">Scan pickup from seller</button>
                </form>
              @endif
              @if($a->kind==='delivery' && $a->parcel_status==='ASSIGNED_TO_RIDER')
                <form class="inline" method="post" enctype="multipart/form-data" action="/rider/parcels/{{ $a->parcel_id }}/status">@csrf
                  <button class="sm" name="status" value="OUT_FOR_DELIVERY"><x-icon name="truck" size="15"/> Out for delivery</button>
                </form>
              @endif
              @if($a->kind==='delivery' && $a->parcel_status==='OUT_FOR_DELIVERY')
                <form class="inline" method="post" enctype="multipart/form-data" action="/rider/parcels/{{ $a->parcel_id }}/status" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">@csrf
                  <input type="file" name="proof" accept=".jpg,.jpeg,.png"><input name="otp" placeholder="Buyer delivery OTP (6 digits)"><input name="cod_amount" type="number" step="0.01" placeholder="Exact COD collected"><button class="sm" name="status" value="DELIVERED">Delivered</button>
                  <select name="reason"><option value="">Failure reason</option><option>Buyer not home</option><option>Wrong address</option><option>Refused</option></select>
                  <button class="sm danger" name="status" value="DELIVERY_FAILED">Delivery failed</button>
                </form>
              @endif
            @endif
          </div>
        </div>
      @empty
        <div class="empty">No assignments yet.</div>
      @endforelse
    </div>
    {{ $assignments->links('components.pagination') }}
  </div>
</div>
@endsection

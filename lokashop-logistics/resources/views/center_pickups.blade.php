@extends('dashboard')
@section('title','Pickup requests')
@section('panel')
<div class="page-head">
  <div><h1>Pickups</h1><p>Approve seller requests and assign a rider covering the seller barangay. Offers expire after 3 hours without acceptance. Riders complete pickup when they return to the center.</p></div>
</div>

@include('operation_tabs',['tabTitle'=>'Pickup','tabOptions'=>['request'=>'Pickup request','ongoing'=>'Ongoing pickup','completed'=>'Completed pickup']])
<div class="panel-card">
  <div class="p-body flush">
    <div class="row-list">
      @forelse($requests as $x)
        <div class="row-item">
          <span class="ico"><x-icon name="box"/></span>
          <div class="info">
            <b>{{ $x->tracking_code }} · Order #{{$x->order_id}}</b>
            <small>Pickup: {{ $origins[$x->id]->barangay ?? '' }}, {{ $origins[$x->id]->city ?? '' }} &middot; Destination: {{ $x->city }}, {{ $x->province }}</small>
          </div>
          <x-badge :status="$x->status" />
          <div class="actions">
            @if($x->status==='expired'&&$x->rider_id===null)<small>Seller request expired after 12 hours. Waiting for the seller to request pickup again.</small>
            @elseif(in_array($x->status,['requested','expired','declined']))
            <form class="inline" action="/center/pickups/{{ $x->id }}/verify" method="post">@csrf
              <select name="rider_id" required aria-label="Pickup area rider"><option value="">Select area rider</option>@foreach($eligible[$x->id] as $rider)<option value="{{$rider->id}}">{{$rider->name}}</option>@endforeach</select>
              @if($eligible[$x->id]->isEmpty())<small>Create an area for the seller barangay and assign an approved local rider first.</small>@endif
              <button class="sm" @disabled($eligible[$x->id]->isEmpty())><x-icon name="check" size="15"/> Assign pickup rider</button>
            </form>
            @elseif($x->status==='completed')<small>Pickup completed · Returned to sorting center · {{ $x->updated_at }}</small>
            @else
              <small>Rider #{{$x->rider_id}} · {{$x->parcel_status==='PICKED_UP'?'Next: rider returns parcel to center':($x->status==='accepted'?'Next: rider collects and scans parcel':'Next: rider accepts pickup')}}@if($x->status==='offered') · Accept before {{\App\Services\PickupRequests::deadline($x)->format('M j g:i A')}}@endif</small>
            @endif
          </div>
        </div>
      @empty
        <div class="empty">No pickups in this stage.</div>
      @endforelse
    </div>
    {{ $requests->links('components.pagination') }}
  </div>
</div>
@endsection

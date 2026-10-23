@extends('dashboard')
@section('title','Pickup requests')
@section('panel')
<div class="page-head">
  <div><h1>Seller pickup requests</h1><p>Approve seller requests and assign a rider covering the seller barangay. Offers expire after 3 hours without acceptance. Riders complete pickup when they return to the center.</p></div>
</div>

<div class="panel-card">
  <div class="p-body flush">
    <div class="row-list">
      @forelse($requests as $x)
        <div class="row-item">
          <span class="ico"><x-icon name="box"/></span>
          <div class="info">
            <b>{{ $x->tracking_code }}</b>
            <small>Pickup: {{ $origins[$x->id]->barangay ?? '' }}, {{ $origins[$x->id]->city ?? '' }} &middot; Destination: {{ $x->city }}, {{ $x->province }}</small>
          </div>
          <x-badge :status="$x->status" />
          <div class="actions">
            @if(in_array($x->status,['requested','expired','declined']))
            <form class="inline" action="/center/pickups/{{ $x->id }}/verify" method="post">@csrf
              <select name="rider_id" required aria-label="Pickup area rider"><option value="">Select area rider</option>@foreach($eligible[$x->id] as $rider)<option value="{{$rider->id}}">{{$rider->name}}</option>@endforeach</select>
              @if($eligible[$x->id]->isEmpty())<small>Create an area for the seller barangay and assign an approved local rider first.</small>@endif
              <button class="sm" @disabled($eligible[$x->id]->isEmpty())><x-icon name="check" size="15"/> Assign pickup rider</button>
            </form>
            @else
              <small>Rider #{{$x->rider_id}} · {{$x->parcel_status==='PICKED_UP'?'Collected; returning to center':ucfirst($x->status)}}@if($x->status==='offered') · Accept before {{\App\Services\PickupRequests::deadline($x)->format('M j g:i A')}}@endif</small>
            @endif
          </div>
        </div>
      @empty
        <div class="empty">No pending pickup requests.</div>
      @endforelse
    </div>
    {{ $requests->links('components.pagination') }}
  </div>
</div>
@endsection

@extends('dashboard')
@section('title','Pickup requests')
@section('panel')
<div class="page-head">
  <div><h1>Seller pickup requests</h1><p>Verify parcels a rider has collected before they enter your center.</p></div>
</div>

<div class="panel-card">
  <div class="p-body flush">
    <div class="row-list">
      @forelse($requests as $x)
        <div class="row-item">
          <span class="ico"><x-icon name="box"/></span>
          <div class="info">
            <b>{{ $x->tracking_code }}</b>
            <small>Rider #{{ $x->rider_id }} &middot; Destination: {{ $x->city }}, {{ $x->province }}</small>
          </div>
          <x-badge status="REQUESTED" />
          <div class="actions">
            <form class="inline" action="/center/pickups/{{ $x->id }}/verify" method="post">@csrf
              <button class="sm"><x-icon name="check" size="15"/> Verify pickup request</button>
            </form>
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

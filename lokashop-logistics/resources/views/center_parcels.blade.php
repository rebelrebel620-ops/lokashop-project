@extends('dashboard')
@section('title','Sorting center overview')
@section('panel')
@php
  $items = $parcels->getCollection();
  $awaiting = $items->where('status','PICKED_UP')->count();
  $atCenter = $items->where('status','AT_SORTING_CENTER')->count();
  $readyToAssign = $items->whereIn('status',['SORTED','DELIVERY_FAILED'])->count();
  $outForDelivery = $items->where('status','OUT_FOR_DELIVERY')->count();
@endphp

<div class="page-head">
  <div><h1>Sorting center overview</h1><p>Keep pickups moving and every parcel in the right hands.</p></div>
</div>

<div class="stat-grid">
  <div class="stat-card"><span class="ico c-amber"><x-icon name="scan"/></span><div><div class="num">{{ $awaiting }}</div><div class="lbl">Awaiting scan</div></div></div>
  <div class="stat-card"><span class="ico c-green"><x-icon name="building"/></span><div><div class="num">{{ $atCenter }}</div><div class="lbl">At sorting center</div></div></div>
  <div class="stat-card"><span class="ico c-blue"><x-icon name="box"/></span><div><div class="num">{{ $readyToAssign }}</div><div class="lbl">Ready to assign</div></div></div>
  <div class="stat-card"><span class="ico c-teal"><x-icon name="truck"/></span><div><div class="num">{{ $outForDelivery }}</div><div class="lbl">Out for delivery</div></div></div>
</div>

<div class="panel-card">
  <div class="p-head"><h2>Parcel operations</h2></div>
  <div class="p-body flush table-wrap">
    <table class="data">
      <thead><tr><th>Tracking ID</th><th>Destination</th><th>Current step</th><th>Action</th></tr></thead>
      <tbody>
      @forelse($parcels as $p)
        <tr>
          <td class="mono">{{ $p->tracking_code }}</td>
          <td>{{ $p->city }}, {{ $p->province }}</td>
          <td><x-badge :status="$p->status" /></td>
          <td>
            @if($p->status==='PICKED_UP')
              <form class="inline" method="post" action="/center/parcels/{{ $p->id }}/receive">@csrf
                <button class="sm"><x-icon name="scan" size="15"/> Scan received</button>
              </form>
            @endif
            @if($p->status==='AT_SORTING_CENTER')
              <form class="inline" method="post" action="/center/parcels/{{ $p->id }}/sort" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">@csrf
                <select name="area_id" style="width:auto;margin:0;padding:8px 10px">
                  @foreach($areas as $area)<option value="{{ $area->id }}">{{ $area->name }} — {{ $area->city }}</option>@endforeach
                </select>
                <button class="sm"><x-icon name="box" size="15"/> Sort parcel</button>
              </form>
            @endif
            @if(in_array($p->status,['SORTED','DELIVERY_FAILED']))
              <form class="inline" method="post" action="/center/parcels/{{ $p->id }}/assign">@csrf
                <button class="sm"><x-icon name="user" size="15"/> Assign rider</button>
              </form>
            @endif
            @if($p->status==='DELIVERY_FAILED')
              <form class="inline" method="post" action="/center/parcels/{{ $p->id }}/return">@csrf
                <button class="sm danger">Return parcel</button>
              </form>
            @endif
            @if(!in_array($p->status,['PICKED_UP','AT_SORTING_CENTER','SORTED','DELIVERY_FAILED']))
              <small>&mdash;</small>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="4" class="empty">No parcels yet.</td></tr>
      @endforelse
      </tbody>
    </table>
    {{ $parcels->links('components.pagination') }}
  </div>
</div>
@endsection

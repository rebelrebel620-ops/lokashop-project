@extends('dashboard')
@section('title','Parcels')
@section('panel')
@php
  $items = $parcels->getCollection();
  $awaiting = $items->where('status','PICKED_UP')->count();
  $atCenter = $items->where('status','AT_SORTING_CENTER')->count();
  $readyToAssign = $items->whereIn('status',['SORTED','DELIVERY_FAILED'])->count();
  $outForDelivery = $items->where('status','OUT_FOR_DELIVERY')->count();
@endphp

<div class="page-head">
  <div><h1>Parcels</h1><p>Keep pickups moving and every parcel in the right hands.</p></div>
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
          <td>@php $destination=\App\Services\AreaRouting::address($p);$matchedAreas=\App\Services\AreaRouting::areas($p); @endphp
           {{$destination->barangay ?? ''}}, {{$destination->city ?? ''}}, {{$destination->province ?? ''}}
           <br><small>Area: {{$matchedAreas->pluck('name')->implode(', ') ?: 'No local match — check coverage or center transfer'}}</small></td>
          <td><x-badge :status="$p->status" /></td>
          <td>
            @if($p->status==='PICKED_UP')
              <form class="inline" method="post" action="/center/parcels/{{ $p->id }}/receive">@csrf
                <button class="sm"><x-icon name="scan" size="15"/> Scan received</button>
              </form>
            @endif
            @if(in_array($p->status,['AT_SORTING_CENTER','SORTED','DELIVERY_FAILED','ASSIGNED_TO_RIDER','OUT_FOR_DELIVERY']))
              <a class="btn sm" href="/center/delivery">Open Delivery</a>
            @endif
            @if($p->status==='DELIVERY_FAILED')
              <form class="inline" method="post" action="/center/parcels/{{ $p->id }}/return">@csrf
                <button class="sm danger">Return parcel</button>
              </form>
            @endif
            @if(!in_array($p->status,['PICKED_UP','AT_SORTING_CENTER','SORTED','DELIVERY_FAILED','ASSIGNED_TO_RIDER']))
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

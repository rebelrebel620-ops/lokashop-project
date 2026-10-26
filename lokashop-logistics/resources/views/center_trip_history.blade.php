@extends('dashboard')
@section('title','Trip history')
@section('panel')
<div class="page-head"><div><h1>Trip history</h1><p><a href="/ops/transfers">Back to active transfers</a></p></div></div>
@forelse($trips as $trip)<div class="panel-card" style="padding:24px"><h2>Trip #{{$trip['id']}} · {{ucfirst($trip['status'])}}</h2><p>Driver #{{$trip['driver_id']}} · {{$trip['completed_at']??''}}</p>@foreach($trip['stops'] as $stop)<h3>{{$stop['center_name']}} · {{$stop['received_at']?'Received':'Not received under this trip'}} @if($stop['received_by_name'])· {{$stop['received_by_name']}}@endif</h3><ul>@foreach($stop['parcels'] as $item)<li>{{$item->tracking_code}} · Order #{{$item->order_id}} · {{$item->received_at?'Received':'Not received under this trip'}}</li>@endforeach</ul>@endforeach</div>@empty<div class="panel-card" style="padding:24px">No closed trips.</div>@endforelse
{{$page->links('components.pagination')}}
@endsection

@extends('dashboard')
@section('panel')<div class="page-head"><div><h1>Center transfers</h1><p>Drivers move parcels between sorting centers. Local riders handle customer pickups and deliveries.</p></div></div>
<div class="panel-card" style="padding:20px"><h2>Send to the destination center</h2>
@forelse($parcels as $p)
@php $address=$p->address_snapshot?json_decode($p->address_snapshot):\Illuminate\Support\Facades\DB::table('addresses')->find($p->address_id);$destination=\App\Services\Municipalities::centerFor($address); @endphp
<div class="row-item"><div><b>{{$p->tracking_code}}</b><p>{{$address->city??''}}, {{$address->province??''}} · {{$destination->name??'No destination center registered'}}</p></div>
@if($destination&&$destination->id!==$p->center_id)<form method="post" action="/center/transfers/{{$p->id}}/offer">@csrf<label>Online driver<select name="rider_id" required><option value="">Choose driver</option>@foreach($drivers as $driver)<option value="{{$driver->id}}">{{$driver->name}} · {{$driver->vehicle}}</option>@endforeach</select></label><button class="btn">Offer transfer</button></form>@else<p>Keep local parcels here for sorting. Register and approve a destination center before inter-municipality delivery.</p>@endif</div>
@empty<p>No parcels awaiting a center transfer.</p>@endforelse</div>
<div class="panel-card" style="padding:20px"><h2>Incoming transfers</h2>@forelse($incoming as $p)<div class="row-item"><b>{{$p->tracking_code}}</b><form method="post" action="/center/transfers/{{$p->id}}/receive">@csrf<button class="btn">Confirm received at this center</button></form></div>@empty<p>No incoming transfers.</p>@endforelse</div>
@endsection

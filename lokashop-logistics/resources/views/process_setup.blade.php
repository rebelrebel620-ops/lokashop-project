@extends('dashboard')
@section('title','Setup and management')
@section('panel')
<div class="page-head"><div><h1>Setup and management</h1><p>Configure coverage and staff here. Daily parcel work stays in Pickups, Transfers and Deliveries.</p></div></div>
<div class="grid">@foreach(['Areas and barangay coverage'=>'/center/areas','Rider applications'=>'/center/approvals','Riders and drivers'=>'/ops/riders','Shipping rates'=>'/ops/rates','COD remittances'=>'/ops/cod','Returns'=>'/ops/returns','Parcel exceptions and corrections'=>'/ops/tracking','Reports'=>'/center/reports'] as $label=>$url)<a class="panel-card" style="padding:24px" href="{{$url}}">{{$label}} →</a>@endforeach</div>
@endsection

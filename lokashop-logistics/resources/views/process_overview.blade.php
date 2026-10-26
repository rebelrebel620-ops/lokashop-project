@extends('dashboard')
@section('title','Logistics overview')
@section('panel')
<div class="page-head"><div><h1>{{$center->name}}</h1><p>{{$center->city}}, {{$center->province}} · Follow the parcel through three steps.</p></div></div>
<div class="grid">
 @foreach([['Pickups',$pickup,'/center/pickups','Collect parcels from local sellers and return them here.'],['Transfers',$transfer,'/ops/transfers','Send to another municipality or receive incoming parcels.'],['Deliveries',$delivery,'/center/delivery','Assign local delivery riders for buyers in this municipality.']] as [$name,$count,$url,$help])
 <div class="panel-card" style="padding:24px"><h2>{{$name}} · {{$count}}</h2><p>{{$help}}</p><a class="btn" href="{{$url}}">Open {{$name}}</a></div>
 @endforeach
</div>
<div class="panel-card" style="padding:24px"><h2>What happens after pickup?</h2><p><b>Buyer in your municipality:</b> the parcel appears in Deliveries.</p><p><b>Buyer in another municipality:</b> the parcel appears in Transfers. The destination center is detected automatically.</p><p>Delivered and closed parcels move to <a href="/center/history">History</a>.</p></div>
@endsection

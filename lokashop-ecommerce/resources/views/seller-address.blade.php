@extends('app')
@section('title','Store address - LokaShop')
@section('content')
<div class="page-head"><div><h1>Store address</h1><p>Manage your pickup and account address.</p></div></div>
<div class="form-card" style="max-width:760px;margin:20px auto"><h2>Account address</h2>@php $savedAddress=\App\Services\Municipalities::address(auth()->id()); @endphp
<form method="post" action="/account/address">@csrf
@foreach(['recipient'=>'Recipient','phone'=>'Phone','region'=>'Region','province'=>'Province','city'=>'City / municipality','barangay'=>'Barangay','street'=>'Street / house number','postal_code'=>'Postal code'] as $field=>$label)<label>{{$label}}<input name="{{$field}}" value="{{old($field,$savedAddress->$field??($field==='recipient'?auth()->user()->name:($field==='phone'?auth()->user()->phone:'')))}}" @required($field!=='region')></label>@endforeach
@if(in_array(auth()->user()->role,['sorting_center','rider']))<p>Your registered municipality stays the same. Update your street and barangay within it.</p>@endif<button class="btn">Save address</button></form></div>

@endsection

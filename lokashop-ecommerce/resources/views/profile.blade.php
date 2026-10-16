@extends('app')
@section('title','My Account - LokaShop')
@section('content')
<div class="page-head"><div><h1>My account</h1><p>Manage your personal details, picture and security settings.</p></div></div>

<div style="max-width:760px;margin:auto">
 <div class="form-card">
  <h3>Account details</h3>
  <form method="post" action="/profile" enctype="multipart/form-data">@csrf
@if(in_array($user->role,['buyer','seller','rider','sorting_center']))
   @if($user->profile_photo)<img src="{{\App\Services\Support::imageUrl($user->profile_photo)}}" alt="Profile picture" style="width:96px;height:96px;object-fit:cover;border-radius:50%">@endif
   <label>Profile picture<input type="file" name="profile_photo" accept="image/jpeg,image/png"></label><p><small>JPG or PNG, up to 5 MB.</small></p>
   @endif
   <label>Full name<input name="name" value="{{$user->name}}" required></label>
   <label>Phone<input name="phone" value="{{$user->phone}}" required></label>
   <p><small>{{$user->email}} &middot; {{ucfirst(str_replace('_',' ',$user->role))}}</small></p>
   <button class="btn">Save account</button>
  </form>
 </div>

</div>
<div class="form-card" style="max-width:760px;margin:20px auto" id="security"><h2>Password and contact details</h2>
@foreach([
 ['label'=>'Change password','action'=>'/tools/password','fields'=>['current_password:password','password:password','password_confirmation:password']],
 ['label'=>'Change email or phone','action'=>'/tools/contact/request','fields'=>['email:email?','phone?','password:password']],
 ['label'=>'Verify contact change','action'=>'/tools/contact/verify','fields'=>['otp']]
] as $form)@include('tool-form',['form'=>$form])@endforeach</div>
<div class="form-card" style="max-width:760px;margin:20px auto"><h2>Account address</h2>@php $savedAddress=\App\Services\Municipalities::address(auth()->id()); @endphp
<form method="post" action="/account/address">@csrf
@foreach(['recipient'=>'Recipient','phone'=>'Phone','region'=>'Region','province'=>'Province','city'=>'City / municipality','barangay'=>'Barangay','street'=>'Street / house number','postal_code'=>'Postal code'] as $field=>$label)<label>{{$label}}<input name="{{$field}}" value="{{old($field,$savedAddress->$field??($field==='recipient'?auth()->user()->name:($field==='phone'?auth()->user()->phone:'')))}}" @required($field!=='region')></label>@endforeach
@if(in_array(auth()->user()->role,['sorting_center','rider']))<p>Your registered municipality stays the same. Update your street and barangay within it.</p>@endif<button class="btn">Save address</button></form></div>
@endsection

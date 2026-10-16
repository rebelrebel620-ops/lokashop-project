@extends('dashboard')
@section('title','Account')
@section('panel')
<div class="page-head"><div><h1>My account</h1></div></div>

<div class="form-card" style="max-width:560px">
  <h2><x-icon name="gear" size="17"/> Account details</h2>
  <form method="post" action="/profile" enctype="multipart/form-data">
    @csrf
@if(in_array($user->role,['buyer','seller','rider','sorting_center']))
   @if($user->profile_photo)<img src="{{\App\Services\Support::imageUrl($user->profile_photo)}}" alt="Profile picture" style="width:96px;height:96px;object-fit:cover;border-radius:50%">@endif
   <label>Profile picture<input type="file" name="profile_photo" accept="image/jpeg,image/png"></label><p><small>JPG or PNG, up to 5 MB.</small></p>
   @endif
    <label>Name</label>
    <input name="name" value="{{ $user->name }}" required>
    <label>Phone</label>
    <input name="phone" value="{{ $user->phone }}" required>
    <p style="color:var(--muted)">{{ $user->email }} &middot; {{ ucfirst(str_replace('_',' ',$user->role)) }}</p>
    <button>Save account</button>
  </form>
</div>

<div class="panel-card">
  <div class="p-head"><h2><x-icon name="bell" size="17"/> Notifications</h2></div>
  <div class="p-body flush">
    <div class="row-list">
      @forelse($notifications as $n)
        <div class="row-item">
          <span class="ico"><x-icon name="bell" size="16"/></span>
          <div class="info"><b>{{ $n->body }}</b><small>{{ $n->created_at }}</small></div>
        </div>
      @empty
        <div class="empty">No notifications yet.</div>
      @endforelse
    </div>
  </div>
</div>
<div class="form-card"><h2>Account address</h2>@php $savedAddress=\App\Services\Municipalities::address(auth()->id()); @endphp
<form method="post" action="/account/address">@csrf
@foreach(['recipient'=>'Recipient','phone'=>'Phone','region'=>'Region','province'=>'Province','city'=>'City / municipality','barangay'=>'Barangay','street'=>'Street / house number','postal_code'=>'Postal code'] as $field=>$label)<label>{{$label}}<input name="{{$field}}" value="{{old($field,$savedAddress->$field??($field==='recipient'?auth()->user()->name:($field==='phone'?auth()->user()->phone:'')))}}" @required($field!=='region')></label>@endforeach
@if(in_array(auth()->user()->role,['sorting_center','rider']))<p>Your registered municipality stays the same. Update your street and barangay within it.</p>@endif<button class="btn">Save address</button></form></div>
@endsection

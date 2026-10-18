@extends('dashboard')
@section('title','My account')
@section('panel')
@php
 $savedAddress=\App\Services\Municipalities::address($user->id);
 $canPhoto=in_array($user->role,['buyer','seller','rider','sorting_center']);
 $isCommerce=config('app.site_kind')==='ecommerce';
 $roleName=$user->role==='rider'?ucfirst($user->courier_type??'rider'):ucfirst(str_replace('_',' ',$user->role));
@endphp
<div class="page-head"><div><span class="design-eyebrow">YOUR PERSONAL SPACE</span><h1>My account</h1><p>Keep your profile, address and contact details up to date.</p></div></div>
<div class="account-workspace">
 <aside class="identity-card">
  <div class="identity-cover"></div>
  <div class="identity-avatar" id="profile-avatar">@if($user->profile_photo)<img src="{{\App\Services\Support::imageUrl($user->profile_photo)}}" alt="Your profile picture">@else<span>{{mb_strtoupper(mb_substr($user->name,0,1))}}</span>@endif</div>
  <h2>{{$user->name}}</h2><span class="design-pill">{{$roleName}}</span><p class="identity-email">{{$user->email}}</p>
  <nav class="account-jumps" aria-label="Account sections"><a href="#personal-details">Personal details</a><a href="#account-address">Delivery &amp; contact address</a>@if($isCommerce)<a href="#security">Password &amp; security</a>@endif</nav>
  <div class="identity-note"><strong>Your account, in one place.</strong><p>Profile changes are saved to your existing account.</p></div>
 </aside>
 <div class="account-panels">
  <section class="design-card" id="personal-details"><div class="design-card-heading"><div><h2>Personal details</h2><p>A familiar face and the right name make every interaction easier.</p></div><span class="design-step">01</span></div>
   <form method="post" action="/profile" enctype="multipart/form-data">@csrf
    @if($canPhoto)<div class="photo-picker"><label for="profile-photo" class="upload-label">Choose profile photo</label><input id="profile-photo" type="file" name="profile_photo" accept="image/jpeg,image/png"><small>JPG or PNG · up to 5 MB</small><p id="profile-photo-feedback" role="status"></p></div>@endif
    <div class="design-field-grid"><label>Full name<input name="name" value="{{old('name',$user->name)}}" autocomplete="name" required></label><label>Registered phone<input name="phone" value="{{$user->phone}}" autocomplete="tel" readonly><small>@if($isCommerce)Use the verified contact change below to update your number.@else Contact your administrator to change this number.@endif</small></label></div>
    <label>Email address<input type="email" value="{{$user->email}}" readonly></label><div class="design-form-footer"><small>Review your details before saving.</small><button class="btn" type="submit">Save profile</button></div>
   </form>
  </section>
  <section class="design-card" id="account-address"><div class="design-card-heading"><div><h2>Account address</h2><p>Your saved address for deliveries and account records.</p></div><span class="design-step">02</span></div>
   <form method="post" action="/account/address">@csrf<div class="design-field-grid">
    @foreach(['recipient'=>'Recipient name','phone'=>'Contact number','region'=>'Region','province'=>'Province','city'=>'City / municipality','barangay'=>'Barangay','street'=>'Street / house number','postal_code'=>'Postal code'] as $field=>$label)<label @if($field==='street') class="field-wide" @endif>{{$label}}<input name="{{$field}}" value="{{old($field,$savedAddress->$field??($field==='recipient'?$user->name:($field==='phone'?$user->phone:'')))}}" @required($field!=='region')></label>@endforeach
   </div>@if(in_array($user->role,['sorting_center','rider']))<p class="design-hint">Your registered municipality stays the same. You can update your street and barangay within it.</p>@endif<div class="design-form-footer"><small>Use a complete, accurate address.</small><button class="btn">Save address</button></div></form>
  </section>
  @if($isCommerce)
  <section class="design-card" id="security"><div class="design-card-heading"><div><h2>Password &amp; security</h2><p>Protect your account and verify changes to your contact details.</p></div><span class="design-step">03</span></div>
   <div class="security-sections"><section><h3>Change password</h3><form method="post" action="/tools/password">@csrf
    <label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label><div class="design-field-grid"><label>New password<input type="password" name="password" autocomplete="new-password" required></label><label>Confirm new password<input type="password" name="password_confirmation" autocomplete="new-password" required></label></div><button class="btn">Update password</button></form></section>
    <section><h3>Change email or phone</h3><p class="design-hint">Enter only the contact detail you want to change. Verification is required.</p>@include('tool-form',['form'=>['label'=>'Send verification code','action'=>'/tools/contact/request','fields'=>['email:email?','phone?','password:password']]])</section>
    <section><h3>Verify contact change</h3>@include('tool-form',['form'=>['label'=>'Verify and save','action'=>'/tools/contact/verify','fields'=>['otp']]])</section>
   </div>
  </section>
  @else
  <section class="design-card"><div class="design-card-heading"><div><h2>Recent notifications</h2><p>The latest updates for your account.</p></div></div><div class="account-notifications">@forelse($notifications as $n)<article><span class="notification-dot"></span><div><p>{{$n->body}}</p><time>{{\Illuminate\Support\Carbon::parse($n->created_at)->format('M j, Y · g:i A')}}</time></div></article>@empty<p class="design-empty">You're all caught up.</p>@endforelse</div></section>
  @endif
 </div>
</div>

@endsection

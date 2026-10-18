@extends('layout')
@section('title', ($register ? 'Register' : 'Log in') . ' - LokaShop')
@section('body-class', $register ? 'register-page' : 'login-page')
@section('page-styles')
<link rel="stylesheet" href="{{ asset('css/login.css') }}">
@if($register)<link rel="stylesheet" href="{{ asset('css/registration.css') }}">@endif
@endsection
@section('content')
@if(!$register)
 @include('login-panel')
@else
<style>.registration-form{max-width:700px;margin:24px auto}.registration-form label{display:block;margin:12px 0}.field-error{color:#a11;display:block;font-size:14px}.registration-form fieldset{border:1px solid #ddd;border-radius:12px;margin:18px 0;padding:16px}.registration-form input[type=checkbox]{width:auto}.password-row{display:flex;gap:8px;align-items:center}.password-row input{flex:1;min-width:0}.password-row button{width:auto}</style>
<div class="registration-intro"><span class="login-eyebrow">JOIN YOUR LOCAL MARKETPLACE</span><h2>Local finds. New possibilities.</h2><p>Shop your favorites or grow your business with LokaShop.</p></div>
<div class="card registration-form">
 <img src="/logo.jpg" width="64" alt="LokaShop" style="border-radius:14px">
 <h1>{{ $register ? 'Create your account' : 'Welcome back' }}</h1>
 <p>{{ $register ? 'Verify your email to activate a buyer account. Sellers also need admin approval.' : 'Log in to continue to LokaShop.' }}</p>
 <form id="account-form" action="{{ $register ? '/register' : '/login' }}" method="post" enctype="multipart/form-data">@csrf
  @if($register)
  <h2 class="registration-section-title">01 <span>About you</span></h2>
  <label class="registration-role">I am a<select name="role" id="account-role">@foreach($roles as $role)<option value="{{$role}}" @selected(old('role','buyer')===$role)>{{ucfirst($role)}}</option>@endforeach</select>@error('role')<small class="field-error">{{$message}}</small>@enderror</label>
  @foreach(['first_name'=>'First name','last_name'=>'Last name'] as $field=>$label)
  <label>{{$label}}<input name="{{$field}}" required minlength="2" maxlength="50" value="{{old($field)}}" autocomplete="{{$field==='first_name'?'given-name':'family-name'}}">@error($field)<small class="field-error">{{$message}}</small>@enderror</label>
  @endforeach
  <label>Mobile number<input type="tel" name="phone" required maxlength="13" pattern="(09[0-9]{9}|\+639[0-9]{9})" placeholder="09171234567 or +639171234567" value="{{old('phone')}}">@error('phone')<small class="field-error">{{$message}}</small>@enderror</label>
  <div data-role="buyer"><label>Date of birth<input type="date" name="date_of_birth" required max="{{now()->subYears(18)->toDateString()}}" value="{{old('date_of_birth')}}">@error('date_of_birth')<small class="field-error">{{$message}}</small>@enderror</label><small>You must be at least 18 years old.</small></div>
  <fieldset data-role="seller"><legend>Shop information</legend>
   <label>Shop name<input name="business_name" required minlength="3" maxlength="30" value="{{old('business_name')}}">@error('business_name')<small class="field-error">{{$message}}</small>@enderror</label>
   <label>Shop category<select name="shop_category" required><option value="">Choose a category</option>@foreach(config('registration.categories') as $category)<option @selected(old('shop_category')===$category)>{{$category}}</option>@endforeach</select>@error('shop_category')<small class="field-error">{{$message}}</small>@enderror</label>
   <label>Seller type<select name="seller_type" id="seller-type" required>@foreach(['Individual','Sole Proprietorship','Corporation'] as $type)<option @selected(old('seller_type')===$type)>{{$type}}</option>@endforeach</select>@error('seller_type')<small class="field-error">{{$message}}</small>@enderror</label>
   <label>TIN<input name="tin" required maxlength="15" pattern="[0-9]{3}-[0-9]{3}-[0-9]{3}-[0-9]{3}" placeholder="000-000-000-000" value="{{old('tin')}}">@error('tin')<small class="field-error">{{$message}}</small>@enderror</label>
   @foreach(['id_document'=>'Government-issued ID','dti_document'=>'DTI certificate','sec_document'=>'SEC certificate','bir_document'=>'BIR Form 2303'] as $field=>$label)
   <label data-document="{{$field}}">{{$label}}<input type="file" name="{{$field}}" accept=".jpg,.jpeg,.png,.pdf">@error($field)<small class="field-error">{{$message}}</small>@enderror</label>
   @endforeach
   <small>JPG, PNG or PDF. Maximum 5 MB per file. Documents depend on seller type.</small>
  </fieldset>
  <fieldset><legend>02 · Delivery address</legend>
   @foreach(['region'=>'Region','province'=>'Province','city'=>'City / Municipality','barangay'=>'Barangay','street'=>'Street address','postal_code'=>'Postal code'] as $field=>$label)
   <label>{{$label}}<input name="{{$field}}" required maxlength="{{$field==='street'?200:($field==='postal_code'?4:120)}}" @if($field==='postal_code') inputmode="numeric" pattern="[0-9]{4}" @endif value="{{old($field)}}">@error($field)<small class="field-error">{{$message}}</small>@enderror</label>
   @endforeach
  </fieldset>
  @endif
  <h2 class="registration-section-title">03 <span>Account security</span></h2>
  <label class="registration-email">Email<input type="email" name="email" required maxlength="190" value="{{old('email')}}" autocomplete="email">@error('email')<small class="field-error">{{$message}}</small>@enderror</label>
  @foreach($register?['password'=>'Password','password_confirmation'=>'Confirm password']:['password'=>'Password'] as $field=>$label)
  <label>{{$label}}<span class="password-row"><input id="{{$field}}" type="password" name="{{$field}}" required @if($register) minlength="8" autocomplete="new-password" @else autocomplete="current-password" @endif><button type="button" class="btn alt" data-password="{{$field}}" aria-label="Show {{$label}}" aria-pressed="false">Show</button></span>@error($field)<small class="field-error">{{$message}}</small>@enderror</label>
  @endforeach
  @if($register)
  <small class="registration-password-help">Use at least 8 characters, including uppercase and lowercase letters, a number and a special character.</small>
  <label class="registration-terms"><input type="checkbox" name="terms" value="1" required @checked(old('terms'))> I accept the <a href="/terms" target="_blank" rel="noopener">Terms and Privacy Policy</a>.@error('terms')<small class="field-error">{{$message}}</small>@enderror</label>
  @endif
  <button id="submit-account" class="btn lg" style="width:100%">{{ $register ? 'Create account' : 'Log in' }}</button>
 </form>
 <p>@if($register) Already have an account? <a href="/login">Log in</a>@else New here? <a href="/register">Create an account</a>@endif</p>
</div>
<script>
const role=document.getElementById('account-role'),type=document.getElementById('seller-type');
function updateFields(){
 if(!role)return;
 document.querySelectorAll('[data-role]').forEach(section=>{
  const enabled=section.dataset.role===role.value;section.hidden=!enabled;
  section.querySelectorAll('input,select').forEach(input=>input.disabled=!enabled);
 });
 document.querySelectorAll('[data-document]').forEach(label=>{
  const field=label.dataset.document;
  const needed=field==='id_document'||(field==='dti_document'&&type.value==='Sole Proprietorship')||(field==='sec_document'&&type.value==='Corporation')||(field==='bir_document'&&type.value!=='Individual');
  label.hidden=!needed;const input=label.querySelector('input');input.disabled=role.value!=='seller'||!needed;input.required=role.value==='seller'&&needed;
 });
}
role?.addEventListener('change',updateFields);type?.addEventListener('change',updateFields);updateFields();
document.querySelectorAll('[data-password]').forEach(button=>button.addEventListener('click',()=>{
 const input=document.getElementById(button.dataset.password),show=input.type==='password';input.type=show?'text':'password';button.textContent=show?'Hide':'Show';button.setAttribute('aria-pressed',String(show));
}));
document.getElementById('account-form').addEventListener('submit',()=>{
 const button=document.getElementById('submit-account');button.disabled=true;button.textContent='Please wait…';
});
</script>
@endif
@endsection

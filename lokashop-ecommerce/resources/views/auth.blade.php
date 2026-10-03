@extends('layout')
@section('title', ($register ? 'Register' : 'Log in') . ' - LokaShop')
@section('content')
<div class="card auth">
 <img src="/logo.jpg" width="64" alt="LokaShop" style="border-radius:14px">
 <h1>{{ $register ? 'Create your account' : 'Welcome back' }}</h1>
 <p class="sub" style="margin-top:-10px">{{ $register ? 'Buyers can log in immediately. Sellers need e-commerce admin approval.' : 'Log in to continue to LokaShop.' }}</p>
 <form action="{{ $register ? '/register' : '/login' }}" method="post" enctype="multipart/form-data">@csrf
  @if($register)
  <label>I am a<select name="role">@foreach($roles as $role)<option value="{{$role}}">{{ucfirst(str_replace('_',' ',$role))}}</option>@endforeach</select></label>
  <label>Full name<input name="name" required value="{{old('name')}}"></label>
  <label>Phone<input name="phone" required value="{{old('phone')}}"></label>
  <label>Business name (sellers only)<input name="business_name"></label>
  <label>Identification document<input type="file" name="id_document" required></label>
  <label>Business permit (sellers only)<input type="file" name="permit_document"></label>
  @endif
  <label>Email<input type="email" name="email" required value="{{old('email')}}"></label>
  <label>Password<input type="password" name="password" required></label>
  @if($register)
  <label>Confirm password<input type="password" name="password_confirmation" required></label>
  @endif
  <button class="btn lg" style="width:100%">{{ $register ? 'Create account' : 'Log in' }}</button>
 </form>
 <p style="text-align:center;margin-top:14px">
  @if($register) Already have an account? <a href="/login">Log in</a>
  @else New here? <a href="/register">Create an account</a> @endif
 </p>
</div>
@endsection

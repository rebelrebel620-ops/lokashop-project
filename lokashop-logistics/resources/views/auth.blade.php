@extends('layout')
@section('title', $register ? 'Register' : 'Log in')
@section('content')
<div class="auth-wrap">
  <div class="auth-card">
    <div class="logo">L</div>
    <h1>{{ $register ? 'Create your partner account' : 'Welcome back' }}</h1>
    <p class="switch">
      {{ $register ? 'Register as a rider or sorting center.' : 'Log in to manage pickups, parcels and deliveries.' }}
    </p>

    <form action="{{ $register ? '/register' : '/login' }}" method="post" enctype="multipart/form-data">
      @csrf
      @if($register)
        <label>Role</label>
        <select name="role">
          @foreach($roles as $role)
            <option value="{{ $role }}">{{ ucfirst(str_replace('_',' ',$role)) }}</option>
          @endforeach
        </select>

        <div class="field-grid">
          <div><label>Full name</label><input name="name" required value="{{ old('name') }}"></div>
          <div><label>Phone</label><input name="phone" required value="{{ old('phone') }}"></div>
        </div>

        <label>Business / center name (if applicable)</label>
        <input name="business_name">

        <label>Vehicle and plate (rider)</label>
        <input name="vehicle">

        <label>Identification document</label>
        <input type="file" name="id_document" required>

        <label>Permit / OR-CR (seller, center, rider)</label>
        <input type="file" name="permit_document">
      @endif

      <label>Email</label>
      <input type="email" name="email" required value="{{ old('email') }}">

      <label>Password</label>
      <input type="password" name="password" required>

      @if($register)
        <label>Confirm password</label>
        <input type="password" name="password_confirmation" required>
      @endif

      <button class="block">{{ $register ? 'Submit for approval' : 'Log in' }}</button>
    </form>

    <p class="switch">
      @if($register)
        Already have an account? <a href="/login">Log in</a>
      @else
        Need a partner account? <a href="/register">Register here</a>
      @endif
    </p>
  </div>
</div>
@endsection

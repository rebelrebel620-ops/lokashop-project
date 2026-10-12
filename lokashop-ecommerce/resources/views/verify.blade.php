@extends('layout')
@section('title','Verify email - LokaShop')
@section('content')
<div class="card auth">
 <h1>Verify your email</h1><p>Enter the six-digit code sent to {{$email}}. It expires after five minutes.</p>
 <form method="post" action="/verify-email">@csrf
  <label>Verification code<input name="otp" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required></label>
  @error('otp')<p class="error">{{$message}}</p>@enderror
  <button class="btn">Verify email</button>
 </form>
 <form method="post" action="/verify-email/resend" style="margin-top:16px">@csrf<button class="btn alt">Resend code</button></form>
 <p>Wait one minute between resend requests. A new code replaces the old code.</p>
</div>
@endsection

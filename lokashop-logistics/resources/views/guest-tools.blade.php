@extends('layout')
@section('title',$title.' - LokaShop Logistics')
@section('body-class','logistics-registration')
@section('content')
@php $isRegistration=request()->is('register','register/courier'); $isCourier=request()->is('register/courier') || in_array(request('type'),['rider','driver']); @endphp
<div class="partner-intro"><span class="public-eyebrow">GROW WITH LOKASHOP LOGISTICS</span><h1>{{ $isRegistration ? 'Join our delivery network' : $title }}</h1><p>Connect local businesses and communities, one delivery at a time.</p></div>
@if($isRegistration)
<nav class="partner-options" aria-label="Registration type">
 <a href="/register" @if(!$isCourier) aria-current="page" @endif><b>Sorting Center</b><span>Register your company and service municipality</span></a>
 <a href="/register/courier" @if($isCourier) aria-current="page" @endif><b>Couriers (Driver and Rider)</b><span>Choose Rider or Driver in the form below</span></a>
</nav>
@endif
<div class="partner-forms">
@foreach($forms as $form)
<section class="partner-card"><h2>{{ $isRegistration ? ($isCourier ? 'Courier application' : 'Sorting center application') : $form['label'] }}</h2>
@if($isRegistration)<p class="public-muted">Complete all fields. Verify your email after submitting; your application will then be reviewed.</p>@include('partner-registration-form',['form'=>$form])
@else @include('tool-form',['form'=>$form]) @endif
</section>
@endforeach
<p class="partner-login">Already registered? <a href="/login">Log in</a> · <a href="/application/resubmit">Correct a rejected application</a></p>
</div>
@endsection

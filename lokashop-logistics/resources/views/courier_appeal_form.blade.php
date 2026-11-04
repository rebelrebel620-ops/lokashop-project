@extends('layout')
@section('title','Suspension appeal')
@section('content')
<section class="partner-card"><h1>Suspension appeal</h1><p>{{auth()->user()->account_status_reason}}</p>@if(session('success'))<p>{{session('success')}}</p>@endif @foreach($errors->all() as $error)<p>{{$error}}</p>@endforeach
@if(!$appeals->contains('status','pending'))<form method="post" action="/courier/appeal">@csrf<label>Explain your appeal<textarea name="message" minlength="20" maxlength="2000" required>{{old('message')}}</textarea></label><button>Send appeal to sorting center</button></form>@else<p>Your appeal is awaiting review by your sorting center.</p>@endif
@foreach($appeals as $appeal)<p>{{ucfirst($appeal->status)}} · {{$appeal->created_at}}</p><p>{{$appeal->review_reason}}</p>@endforeach<a href="/login">Return to sign in</a></section>
@endsection

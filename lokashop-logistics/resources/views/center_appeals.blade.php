@extends('dashboard')
@section('title','Appeal')
@section('panel')
<div class="page-head"><div><h1>Appeal</h1><p>Review suspension appeals from your couriers in this municipality.</p></div></div>
@forelse($appeals as $appeal)<section class="design-card"><header class="workspace-record-header"><div><h2>{{$appeal->name}}</h2><p>{{ucfirst($appeal->courier_type)}} · {{$appeal->created_at}}</p></div><span class="design-pill">{{ucfirst($appeal->status)}}</span></header><p style="white-space:pre-wrap">{{$appeal->message}}</p><a href="/center/couriers/{{$appeal->user_id}}">View courier details →</a>@if($appeal->status==='pending')<form method="post" action="/center/appeals/{{$appeal->id}}">@csrf<label>Review reason<textarea name="reason" minlength="10" maxlength="300" required></textarea></label><button name="decision" value="approved">Approve & activate</button><button class="danger" name="decision" value="rejected">Reject appeal</button></form>@else<p>Review: {{$appeal->review_reason}}</p>@endif</section>@empty<section class="design-card empty">No appeals from your municipality.</section>@endforelse{{$appeals->links('components.pagination')}}
@endsection

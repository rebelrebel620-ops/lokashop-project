@extends('dashboard')
@section('title',auth()->user()->role==='sorting_center'?'Courier approvals':'Sorting center approvals')
@section('panel')
<link rel="stylesheet" href="{{asset('css/approval-workspace.css').'?v=13'}}"><script src="{{asset('js/application-decision.js')}}" defer></script>
<div class="approval-workspace">
<header class="approval-intro"><span class="approval-eyebrow">{{$isCenter?'COURIER ONBOARDING':'NETWORK ONBOARDING'}}</span><h1>{{$isCenter?'Rider & driver approvals':'Sorting center approvals'}}</h1><p>{{$isCenter?'Review rider and driver details and supporting documents for your sorting center and municipality.':'Review sorting center details and supporting documents before recording your decision.'}}</p></header>
<nav class="approval-tabs" aria-label="Application status">
@foreach(['pending'=>'Pending review','approved'=>'Approved','rejected'=>'Rejected'] as $key=>$label)
<a href="{{$approvalBase}}/approvals?{{http_build_query(array_merge(array_filter($filters,fn($value)=>$value!==''),['status'=>$key]))}}" @if($status===$key) aria-current="page" @endif>{{$label}} <span>{{$counts->get($key,0)}}</span></a>
@endforeach
</nav>
<section class="approval-panel"><form method="get" action="{{$approvalBase}}/approvals" class="approval-filter"><input type="hidden" name="status" value="{{$status}}">
<label class="approval-search">{{$isCenter?'Search riders or drivers':'Search applicants or sorting centers'}}<input name="q" maxlength="150" value="{{$filters['q']}}" placeholder="{{$isCenter?'Name, email, phone, license or plate':'Name, email, phone or sorting center'}}"></label>
@if($isCenter)<label>Courier type<select name="courier_type"><option value="">All couriers</option><option value="rider" @selected($filters['courier_type']==='rider')>Riders</option><option value="driver" @selected($filters['courier_type']==='driver')>Drivers</option></select></label>@endif
<div class="approval-actions"><button type="submit">Search</button><a class="approval-secondary" href="{{$approvalBase}}/approvals?status={{$status}}">Reset</a></div></form></section>
<div class="approval-list-heading"><h2>{{['pending'=>'Applications awaiting review','approved'=>'Approved applications','rejected'=>'Rejected applications'][$status]}}</h2><span>{{$users->total()}} results</span></div>
<div class="approval-record-list">
@forelse($users as $u)
@php
 $address=$addresses->get($u->id);$center=$centers->get($u->id);$documents=$docs->get($u->id,collect());
 $role=$u->role==='rider'?ucfirst($u->courier_type??'rider'):'Sorting center';
 $profileUrl=$isCenter?'/center/couriers/'.$u->id:'/admin/users/'.$u->id;
@endphp
<article class="approval-record">
<header class="approval-record-heading"><div class="approval-person">
@if($u->profile_photo)<img class="approval-avatar" src="{{\App\Services\Support::imageUrl($u->profile_photo)}}" alt="{{$u->name}} profile" loading="lazy">@else<span class="approval-avatar approval-monogram" aria-hidden="true">{{mb_strtoupper(mb_substr($u->name,0,1))}}</span>@endif
<div><h3>{{$center->name??$u->name}}</h3><small>{{$u->name}} · {{$role}} · Account #{{$u->id}}</small></div></div><span class="approval-status {{$status}}">{{$u->approval_status==='pending_activation'?'Awaiting activation':ucfirst($status)}}</span></header>
<p class="approval-meta">Applied {{$u->created_at?\Illuminate\Support\Carbon::parse($u->created_at)->format('M j, Y · g:i A'):'Date not recorded'}} · {{$u->email_verified_at?'Email verified':'Email verification not recorded'}}</p>
<div class="approval-application-grid"><dl class="approval-facts">
<div><dt>Email</dt><dd>{{$u->email}}</dd></div><div><dt>Phone</dt><dd>{{$u->phone?:'Not recorded'}}</dd></div>
@if($isCenter)
<div><dt>Courier type</dt><dd>{{$role}}</dd></div><div><dt>Vehicle</dt><dd>{{$u->vehicle?:'Not recorded'}}</dd></div>
<div><dt>License number</dt><dd>{{$u->license_number?:'Not recorded'}}</dd></div><div><dt>License expiry</dt><dd>{{$u->license_expires_at?\Illuminate\Support\Carbon::parse($u->license_expires_at)->format('M j, Y'):'Not recorded'}}</dd></div>
<div><dt>Plate number</dt><dd>{{$u->plate_number?:'Not recorded'}}</dd></div>
@else
<div><dt>Sorting center</dt><dd>{{$center->name??'Not recorded'}}</dd></div><div><dt>Service municipality</dt><dd>{{$center?($center->city.', '.$center->province):'Not recorded'}}</dd></div>
<div><dt>TIN</dt><dd>{{$u->tin?:'Not recorded'}}</dd></div><div><dt>SEC number</dt><dd>{{$u->sec_number?:'Not recorded'}}</dd></div>
@endif
<div><dt>Registered address</dt><dd>@if($address){{$address->street}}, {{$address->barangay}}, {{$address->city}}, {{$address->province}} {{$address->postal_code}}@else Not recorded @endif</dd></div>
</dl><aside class="approval-documents"><h4>Supporting documents <span>{{$documents->count()}}</span></h4><p>Open each document to check the registration details and the applicant's identity.</p><div class="approval-document-links">
@forelse($documents as $doc)<a href="{{$approvalBase}}/documents/{{$doc->id}}" target="_blank" rel="noopener"><span><strong>{{\App\Services\DocumentLabels::label($doc->kind)}}</strong><small>{{$u->name}} · Document #{{$doc->id}}</small></span><span class="approval-open">Open ↗</span></a>
@empty<p class="approval-notice">No supporting documents found. Review the account details before deciding.</p>@endforelse
</div></aside></div>
@if($status==='rejected'&&$u->rejection_reason)<div class="approval-resolution"><strong>Rejection reason</strong><p>{{$u->rejection_reason}}</p></div>@endif
@if(!$isCenter)<div class="approval-actions"><a class="approval-secondary" href="{{$profileUrl}}">View full sorting center profile</a></div>@endif
@if($isCenter&&$u->approval_status==='pending_activation')
<p class="approval-help">This invited courier must activate their account by email before appearing in the Courier list.</p><form method="post" action="/ops/rider/resend">@csrf<input type="hidden" name="id" value="{{$u->id}}"><div class="approval-actions"><button type="submit">Resend activation email</button></div></form>
@endif
@if($status==='pending'&&$u->approval_status==='pending')
<div class="approval-decision"><button type="button" data-application-dialog="decide-application-{{$u->id}}" aria-haspopup="dialog" aria-controls="decide-application-{{$u->id}}">Decide application</button></div>
<dialog id="decide-application-{{$u->id}}" class="approval-choice-dialog" aria-labelledby="decide-application-title-{{$u->id}}" @if($errors->any()&&(string)old('_approval_id')===(string)$u->id) data-reopen-application-dialog @endif>
<header><div><span class="approval-eyebrow">APPLICATION REVIEW</span><h2 id="decide-application-title-{{$u->id}}">Decide application</h2></div><button type="button" class="approval-choice-close" data-close-application-dialog aria-label="Close application decision">×</button></header>
<p class="approval-choice-person"><strong>{{$u->name}}</strong><br>{{$u->email}}<br><small>{{$role}} · Account #{{$u->id}}</small></p>
@if($errors->any()&&(string)old('_approval_id')===(string)$u->id)<div class="approval-notice" role="alert">@foreach($errors->all() as $error)<p>{{$error}}</p>@endforeach</div>@endif
<form method="post" action="{{$approvalBase}}/approvals/{{$u->id}}">@csrf<input type="hidden" name="_approval_id" value="{{$u->id}}">
<label>Decision note<textarea name="reason" minlength="10" maxlength="300" rows="3" placeholder="Required for rejection: explain what needs correcting (10–300 characters).">{{(string)old('_approval_id')===(string)$u->id?old('reason'):''}}</textarea></label><p class="approval-help">Rejections require a clear reason. The applicant receives the decision through the existing notification process.</p>
<div class="approval-actions"><button type="button" class="approval-secondary" data-close-application-dialog>Cancel</button><button type="submit" name="decision" value="approved">Approve {{$isCenter?strtolower($role):'sorting center'}}</button><button type="submit" class="approval-reject" name="decision" value="rejected">Reject application</button></div></form></dialog>
@endif
</article>
@empty<section class="approval-empty"><span aria-hidden="true">✓</span><h2>{{$filters['q']!==''||$filters['courier_type']!==''?'No matching applications':($status==='pending'?'No applications waiting for review':'No '.$status.' applications yet')}}</h2><p>{{$isCenter?'Only riders and drivers assigned to your sorting center and municipality appear here.':'Sorting center applications appear here after registration and email verification.'}} Use the tabs to view other decisions.</p><a class="approval-secondary" href="{{$approvalBase}}/approvals?status={{$status}}">Reset filters</a></section>@endforelse
</div>
{{$users->links('components.pagination')}}
</div>
@endsection

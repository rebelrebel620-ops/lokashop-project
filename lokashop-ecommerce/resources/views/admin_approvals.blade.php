@extends('app')
@section('title','Seller approvals - Admin')
@section('content')
<link rel="stylesheet" href="{{asset('css/admin-workspace.css')}}"><script src="{{asset('js/admin-workspace.js')}}" defer></script>
<div class="page-head"><div><span class="admin-eyebrow">SELLER ONBOARDING</span><h1>Seller approvals</h1><p>Review seller details and supporting documents before recording your decision. Buyer accounts do not need approval.</p></div></div>
<nav class="admin-tabs" aria-label="Seller registration status">
@foreach(['pending'=>'Pending review','approved'=>'Approved','rejected'=>'Rejected'] as $key=>$label)
<a href="{{url('/admin/approvals').'?'.http_build_query(['status'=>$key])}}" @if($status===$key) aria-current="page" @endif>{{$label}} <span>{{$counts->get($key,0)}}</span></a>
@endforeach
</nav><section class="admin-panel"><form method="get" action="/admin/approvals" class="admin-filter simple"><input type="hidden" name="status" value="{{$status}}"><label>Search sellers or stores<input name="q" maxlength="150" value="{{$filters['q']??''}}" placeholder="Seller, email, store or category"></label><div class="admin-actions"><button class="btn">Search</button><a class="btn alt" href="{{url('/admin/approvals').'?'.http_build_query(['status'=>$status])}}">Reset</a></div></form></section>
<div class="admin-list-heading"><h2>{{['pending'=>'Applications awaiting review','approved'=>'Approved sellers','rejected'=>'Rejected applications'][$status]}}</h2><span>{{$users->total()}} results</span></div>
<div class="admin-record-list">
@forelse($users as $u)
@php $address=$addresses->get($u->id);$documents=$docs->get($u->id,collect()); @endphp
<article class="admin-record"><div class="admin-record-heading"><div class="admin-person">
@if($u->profile_photo)
<img class="admin-person-photo" src="{{\App\Services\Support::imageUrl($u->profile_photo)}}" alt="{{$u->name}} profile" loading="lazy">
@else
<span class="admin-monogram" aria-hidden="true">{{mb_strtoupper(mb_substr($u->name,0,1))}}</span>
@endif
<div><h3>{{$u->store_name?:$u->name}}</h3><small>{{$u->name}} · Seller #{{$u->id}}</small></div></div><span class="admin-status {{$status==='approved'?'positive':($status==='pending'?'warning':'muted')}}">{{ucfirst($status)}}</span></div><p class="admin-meta">Applied {{\Illuminate\Support\Carbon::parse($u->created_at)->format('M j, Y · g:i A')}} · {{$u->email_verified_at?'Email verified':'Email verification not recorded'}}</p>
<div class="admin-application-grid"><dl class="admin-facts"><div><dt>Email</dt><dd>{{$u->email}}</dd></div><div><dt>Phone</dt><dd>{{$u->phone?:'Not recorded'}}</dd></div><div><dt>Main category</dt><dd>{{$u->store_category?:'Not assigned'}}</dd></div><div><dt>Seller type</dt><dd>{{$u->seller_type?:'Not recorded'}}</dd></div><div><dt>TIN</dt><dd>{{$u->tin?:'Not recorded'}}</dd></div><div><dt>Registered address</dt><dd>
@if($address)
{{$address->street}}, {{$address->barangay}}, {{$address->city}}, {{$address->province}} {{$address->postal_code}}
@else
Not recorded
@endif
</dd></div></dl><aside class="admin-documents"><h4>Supporting documents <span>{{$documents->count()}}</span></h4><p class="admin-help">Open each document to check the registration details.</p><div class="admin-document-links">
@forelse($documents as $doc)
<a href="/admin/documents/{{$doc->id}}" target="_blank" rel="noopener">{{ucwords(str_replace('_',' ',$doc->kind))}} <small>Open document</small></a>
@empty
<p class="admin-notice">No supporting documents found. Review the account details before deciding.</p>
@endforelse
</div></aside></div>
@if($status==='rejected'&&$u->rejection_reason)
<div class="admin-resolution"><strong>Rejection reason</strong><p>{{$u->rejection_reason}}</p></div>
@endif
<div class="admin-actions admin-record-actions"><a class="btn alt" href="/admin/users/{{$u->id}}">View full seller profile</a></div>
@if($status==='pending')
<details class="admin-details"><summary>Review application &amp; decide</summary><form method="post" action="/admin/approvals/{{$u->id}}" class="admin-decision-form" data-seller-approval>@csrf<label>Decision note<textarea name="reason" minlength="10" maxlength="300" rows="3" placeholder="Required for rejection: explain what the seller needs to correct (10–300 characters)."></textarea></label><p class="admin-help">Rejections require a clear reason. The seller receives the decision through the existing notification process.</p><div class="admin-actions"><button class="btn" name="decision" value="approved">Approve seller</button><button class="btn alt" name="decision" value="rejected">Reject application</button></div></form></details>
@endif
</article>
@empty
<section class="admin-empty"><span class="admin-empty-symbol" aria-hidden="true">✓</span><h2>{{$status==='pending'?'No applications waiting for review':'No matching '.$status.' sellers'}}</h2><p>New seller applications appear here after registration. Use the other tabs to view earlier decisions.</p><a class="btn alt" href="/admin/users?role=seller">View seller accounts</a></section>
@endforelse
</div>{{$users->links('components.pagination')}}
@endsection

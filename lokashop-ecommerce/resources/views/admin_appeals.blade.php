@extends('app')
@section('title','Suspension appeals - Admin')
@section('content')
<link rel="stylesheet" href="{{asset('css/admin-workspace.css')}}">
<div class="page-head"><div><span class="admin-eyebrow">ACCOUNT REVIEWS</span><h1>Suspension appeals</h1><p>Review the account context and appeal before recording a decision.</p></div></div>
<div class="admin-stats compact"><article><small>Pending appeals</small><strong>{{$counts->get('pending',0)}}</strong><span>Waiting for review</span></article><article><small>Approved</small><strong>{{$counts->get('approved',0)}}</strong><span>Recorded approvals</span></article><article><small>Rejected</small><strong>{{$counts->get('rejected',0)}}</strong><span>Recorded rejections</span></article></div>
<section class="admin-panel"><form method="get" action="/admin/appeals" class="admin-filter"><label>Search appeals<input name="q" maxlength="150" value="{{$filters['q']??''}}" placeholder="Name, email, appeal text or ID"></label><label>Appeal status<select name="status">
@foreach(['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','all'=>'All appeals'] as $key=>$label)
<option value="{{$key}}" @selected($status===$key)>{{$label}}</option>
@endforeach
</select></label><label>Account role<select name="role"><option value="">All roles</option><option value="buyer" @selected(($filters['role']??'')==='buyer')>Buyer</option><option value="seller" @selected(($filters['role']??'')==='seller')>Seller</option></select></label><div class="admin-actions"><button class="btn">Apply filters</button><a class="btn alt" href="/admin/appeals">Reset</a></div></form></section>
<div class="admin-list-heading"><h2>{{$status==='pending'?'Appeals awaiting review':'Appeal history'}}</h2><span>{{$appeals->total()}} results</span></div>
<div class="admin-record-list">
@forelse($appeals as $a)
<article class="admin-record"><div class="admin-record-heading"><div class="admin-person"><span class="admin-monogram" aria-hidden="true">{{mb_strtoupper(mb_substr($a->name,0,1))}}</span><div><h3>{{$a->name}}</h3><small>{{ucfirst($a->role)}} · {{$a->email}}</small></div></div><span class="admin-status {{$a->status==='approved'?'positive':($a->status==='pending'?'warning':'muted')}}">{{ucfirst($a->status)}}</span></div><p class="admin-meta">Appeal #{{$a->id}} · Submitted {{\Illuminate\Support\Carbon::parse($a->created_at)->format('M j, Y · g:i A')}} · Current account: <strong>{{ucfirst($a->account_status)}}</strong></p><div class="admin-case-grid"><section><p class="admin-record-label">APPLICANT’S APPEAL</p><p class="admin-message">{{$a->message}}</p></section><aside class="admin-case-context"><strong>Account context</strong><p>{{$a->account_status_reason?:'No account status note recorded.'}}</p><a href="/admin/users/{{$a->user_id}}">View full account details</a></aside></div>
@if($a->resolution)
<div class="admin-resolution"><strong>Decision reason</strong><p>{{$a->resolution}}</p><small>Recorded {{\Illuminate\Support\Carbon::parse($a->updated_at)->format('M j, Y · g:i A')}}</small></div>
@endif
@if($a->status==='pending'&&$a->account_status==='suspended')
<details class="admin-details"><summary>Review and decide</summary><form method="post" action="/admin/appeals/{{$a->id}}" class="admin-decision-form">@csrf<label>Decision reason<textarea name="resolution" minlength="5" maxlength="3000" required rows="4" placeholder="Explain why the appeal is approved or rejected."></textarea></label><p class="admin-help">Approval restores the suspended account. Rejection keeps it suspended. The decision reason is shared with the applicant.</p><div class="admin-actions"><button class="btn" name="decision" value="approved" onclick="return confirm('Approve this appeal and restore the suspended account?')">Approve &amp; restore account</button><button class="btn alt" name="decision" value="rejected">Reject appeal</button></div></form></details>
@elseif($a->status==='pending')
<p class="admin-notice">This appeal cannot be decided because the account is currently {{$a->account_status}}. Review the account details before taking any action.</p>
@endif
</article>
@empty
<section class="admin-empty"><h2>{{$status==='pending'?'No pending appeals':'No matching appeals'}}</h2><p>Use the filters to view decisions and account history.</p></section>
@endforelse
</div>{{$appeals->links('components.pagination')}}
@endsection

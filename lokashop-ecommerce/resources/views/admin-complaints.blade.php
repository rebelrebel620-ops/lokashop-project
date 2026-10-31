@extends('app')
@section('title','Complaints - Admin')
@section('content')
<link rel="stylesheet" href="{{asset('css/admin-workspace.css')}}">
<div class="page-head"><div><span class="admin-eyebrow">BUYER SUPPORT</span><h1>Complaints</h1><p>Understand the issue, record its resolution and keep a clear history.</p></div></div>
<div class="admin-stats compact"><article><small>Open complaints</small><strong>{{$counts->get('open',0)}}</strong><span>Waiting for a resolution</span></article><article><small>Resolved</small><strong>{{$counts->get('resolved',0)}}</strong><span>Saved resolution history</span></article><article><small>Total complaints</small><strong>{{$counts->sum()}}</strong><span>All complaints on file</span></article></div>
<section class="admin-panel"><form method="get" action="/admin/complaints" class="admin-filter simple"><label>Search complaints<input name="q" maxlength="150" value="{{$filters['q']??''}}" placeholder="Complaint, order, buyer, store or tracking"></label><label>Status<select name="status">
@foreach(['open'=>'Open','resolved'=>'Resolved','all'=>'All complaints'] as $key=>$label)
<option value="{{$key}}" @selected($status===$key)>{{$label}}</option>
@endforeach
</select></label><div class="admin-actions"><button class="btn">Apply filters</button><a class="btn alt" href="/admin/complaints">Reset</a></div></form></section>
<div class="admin-list-heading"><h2>{{$status==='open'?'Needs attention':'Complaint records'}}</h2><span>{{$complaints->total()}} results</span></div>
<div class="admin-record-list">
@forelse($complaints as $c)
<article class="admin-record"><div class="admin-record-heading"><div><h3>Complaint #{{$c->id}}</h3><small>{{\Illuminate\Support\Carbon::parse($c->created_at)->format('M j, Y · g:i A')}} · {{$c->order_id?'Order #'.$c->order_id:'General complaint'}}</small></div><span class="admin-status {{$c->status==='resolved'?'positive':'warning'}}">{{ucfirst($c->status)}}</span></div><div class="admin-case-grid"><section><p class="admin-record-label">BUYER’S COMPLAINT</p><p class="admin-message">{{$c->description}}</p></section><aside class="admin-case-context"><strong>{{$c->buyer_name??'Buyer not found'}}</strong><small>{{$c->buyer_email}}</small>
@if($c->order_id)
<p>Store: {{$c->store_name??'Not recorded'}}<br>Order status: {{ucfirst(strtolower(str_replace('_',' ',$c->order_status??'Not recorded')))}}</p>
@endif
@if($c->tracking_code)
<small>{{$c->tracking_code}}</small>
@endif
<a href="/admin/users/{{$c->user_id}}">View buyer profile</a></aside></div>
@if($c->resolution)
<div class="admin-resolution"><strong>Resolution recorded</strong><p>{{$c->resolution}}</p><small>Updated {{\Illuminate\Support\Carbon::parse($c->updated_at)->format('M j, Y · g:i A')}}</small></div>
@endif
@if($c->status==='open')
<details class="admin-details"><summary>Write a resolution</summary><form method="post" action="/admin/complaints/{{$c->id}}" class="admin-decision-form">@csrf<label>How was this resolved?<textarea name="resolution" minlength="5" maxlength="3000" rows="4" required placeholder="Record what was checked, what action was taken, and the outcome for the buyer."></textarea></label><p class="admin-help">This note is visible to the buyer. Resolving a complaint does not issue a refund or change its order status.</p><button class="btn">Save &amp; resolve complaint</button></form></details>
@endif
</article>
@empty
<section class="admin-empty"><h2>{{$status==='open'?'No open complaints':'No matching complaints'}}</h2><p>Use the status filter to view resolved cases and earlier records.</p></section>
@endforelse
</div>{{$complaints->links('components.pagination')}}
@endsection

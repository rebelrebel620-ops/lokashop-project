@extends('app')
@section('title','Approvals - Admin')
@section('content')
<div class="page-head"><div><h1>Pending registrations</h1><p>Review identity and permit documents before approving new accounts.</p></div></div>

@forelse($users as $u)
<div class="section-card">
 <div class="shead"><h3>{{$u->name}}</h3><span class="badge blue">{{ucfirst(str_replace('_',' ',$u->role))}}</span></div>
 <p><small>{{$u->email}} &middot; {{$u->phone}}</small></p>
 <div class="inline-form">
  @foreach($docs[$u->id]??[] as $doc)<a class="btn sm alt" href="/admin/documents/{{$doc->id}}" target="_blank">View {{$doc->kind}} document</a>@endforeach
 </div>
 <form method="post" action="/admin/approvals/{{$u->id}}" style="margin-top:8px">@csrf
  <button name="decision" value="approved" class="btn">Approve</button>
  <button name="decision" value="rejected" class="btn danger">Reject</button>
 </form>
</div>
@empty
<div class="section-card empty">Nothing waiting for review.</div>
@endforelse
{{$users->links()}}
@endsection

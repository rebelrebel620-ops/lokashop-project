@extends('app')
@section('title','Approvals - Admin')
@section('content')
<div class="page-head"><div><h1>Pending seller registrations</h1><p>Review identity and permit documents before approving sellers. Buyers do not need approval.</p></div></div>

@forelse($users as $u)
<div class="section-card">
 <div class="shead"><h3>{{$u->name}}</h3><span class="badge blue">{{ucfirst(str_replace('_',' ',$u->role))}}</span></div>
 <p><small>{{$u->email}} &middot; {{$u->phone}}</small></p>
 @if(isset($businesses[$u->id]))<p>Shop: {{$businesses[$u->id]->name}} · Category: {{$businesses[$u->id]->category}}</p>@endif
 <p>Seller type: {{$u->seller_type ?? 'Not recorded'}} · TIN: {{$u->tin ?? 'Not recorded'}}</p>
 @if(isset($addresses[$u->id]))<p>Address: {{$addresses[$u->id]->street}}, {{$addresses[$u->id]->barangay}}, {{$addresses[$u->id]->city}}, {{$addresses[$u->id]->province}} {{$addresses[$u->id]->postal_code}}</p>@endif
 <div class="inline-form">
  @foreach($docs[$u->id]??[] as $doc)<a class="btn sm alt" href="/admin/documents/{{$doc->id}}" target="_blank">View {{$doc->kind}} document</a>@endforeach
 </div>
 <form method="post" action="/admin/approvals/{{$u->id}}" style="margin-top:8px">@csrf <input name="reason" placeholder="Reason for rejection (10–300 characters)" minlength="10" maxlength="300">
  <button name="decision" value="approved" class="btn">Approve</button>
  <button name="decision" value="rejected" class="btn danger">Reject</button>
 </form>
</div>
@empty
<div class="section-card empty">Nothing waiting for review.</div>
@endforelse
{{$users->links('components.pagination')}}
@endsection

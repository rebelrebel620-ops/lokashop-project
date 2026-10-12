@extends('app')
@section('title','Accounts - LokaShop')
@section('content')
<div class="page-head"><div><h1>Accounts</h1><p>Buyers verify their email. Sellers also require admin approval.</p></div></div>
<div class="section-card">
 <form method="get" action="{{url('/admin/users')}}">
  <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:end">
   <div style="flex:2;min-width:220px"><label for="account-search">Search name</label><input id="account-search" name="search" value="{{$search}}" placeholder="Enter a buyer or seller name" maxlength="150"></div>
   <div style="flex:1;min-width:180px"><label for="account-role">Account type</label><select id="account-role" name="role"><option value="">All buyers and sellers</option><option value="buyer" @selected($role==='buyer')>Buyers only</option><option value="seller" @selected($role==='seller')>Sellers only</option></select></div>
   <div style="display:flex;gap:8px;margin-bottom:14px"><button class="btn" type="submit">Filter</button><a class="btn outline" href="{{url('/admin/users')}}">Reset</a></div>
  </div>
 </form>
 <p aria-live="polite">{{$users->total()}} {{ $users->total()===1 ? 'account' : 'accounts' }} found.</p>
</div>
<div class="table-card" style="overflow-x:auto">
 <table>
  <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Email verification</th><th>Seller approval</th><th>Active</th><th>Created at</th></tr></thead>
  <tbody>
   @forelse($users as $u)
   <tr>
    <td>{{$u->id}}</td><td>{{$u->name}}</td><td>{{$u->email}}</td>
    <td><span class="badge blue">{{ucfirst($u->role)}}</span></td>
    <td>@if($u->email_verified_at)<span class="badge green">Verified</span>@elseif($u->verification_required)<span class="badge orange">Unverified</span>@else<span class="badge gray">Not required (existing account)</span>@endif</td>
    <td>@if($u->role==='seller')@if($u->approval_status==='unverified')<span class="badge orange">Awaiting email verification</span>@else<span class="badge {{$u->approval_status==='approved'?'green':($u->approval_status==='pending'?'orange':'gray')}}">{{ucfirst($u->approval_status)}}</span>@endif @else<span class="badge gray">Not required</span>@endif</td>
    <td><span class="badge {{$u->active?'green':'gray'}}">{{$u->active?'Active':'Inactive'}}</span></td><td>{{$u->created_at}}</td>
   </tr>
   @empty
   <tr><td colspan="8" class="empty">No accounts match your filters. Try another name or reset the filters.</td></tr>
   @endforelse
  </tbody>
 </table>
</div>
{{$users->links()}}
@endsection

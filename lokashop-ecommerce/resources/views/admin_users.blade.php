@extends('app')
@section('title','Accounts - LokaShop')
@section('content')
<div class="page-head"><div><h1>Accounts</h1><p>Buyers verify their email. Sellers also require admin approval.</p></div></div>
<div class="section-card">
 <form method="get" action="{{url('/admin/users')}}">
  <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:end">
   <div style="flex:2;min-width:220px"><label for="account-search">Search name</label><input id="account-search" name="search" value="{{$search}}" placeholder="Enter a buyer or seller name" maxlength="150"></div>
   <div style="flex:1;min-width:180px"><label for="account-role">Account type</label><select id="account-role" name="role"><option value="">All buyers and sellers</option><option value="buyer" @selected($role==='buyer')>Buyers only</option><option value="seller" @selected($role==='seller')>Sellers only</option></select></div>
   <div><label for="state">Account state</label><select id="state" name="state"><option value="">All except archived</option>@foreach(['active'=>'Active / Restored','suspended'=>'Suspended','banned'=>'Banned','archived'=>'Archive'] as $value=>$label)<option value="{{$value}}" @selected($state===$value)>{{$label}}</option>@endforeach</select></div><div style="display:flex;gap:8px;margin-bottom:14px"><button class="btn" type="submit">Filter</button><a class="btn outline" href="{{url('/admin/users')}}">Reset</a></div>
  </div>
 </form>
 <p aria-live="polite">{{$users->total()}} {{ $users->total()===1 ? 'account' : 'accounts' }} found.</p>
</div>
<div class="table-card" style="overflow-x:auto">
 <table>
  <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Email verification</th><th>Approval</th><th>Status</th><th>Created at</th><th>Account action</th></tr></thead>
  <tbody>
   @forelse($users as $u)
   <tr>
    <td>{{$u->id}}</td><td>{{$u->name}}</td><td>{{$u->email}}</td>
    <td><span class="badge blue">{{ucfirst($u->role)}}</span></td>
    <td>@if($u->email_verified_at)<span class="badge green">Verified</span>@else<span class="badge orange">Unverified</span>@endif</td>
    <td>@if($u->role==='seller')@if($u->approval_status==='unverified')<span class="badge orange">Awaiting email verification</span>@else<span class="badge {{$u->approval_status==='approved'?'green':($u->approval_status==='pending'?'orange':'gray')}}">{{ucfirst($u->approval_status)}}</span>@endif @else<span class="badge gray">Not required</span>@endif</td>
    <td><span class="badge {{$u->active?'green':'gray'}}">{{ucfirst($u->account_status)}}</span></td><td>{{$u->created_at}}</td><td>@if($u->account_status==='banned')Permanently banned @else<form method="post" action="/admin/users/{{$u->id}}/status">@csrf
<select name="status" aria-label="Account action">@foreach(['active'=>'Active/Restored','suspended'=>'Suspend','banned'=>'Banned','archived'=>'Archive'] as $value=>$label)<option value="{{$value}}" @selected(($u->account_status??'active')===$value)>{{$label}}</option>@endforeach</select>
<input name="reason" required minlength="5" maxlength="300" placeholder="Reason for change"><button class="btn sm">Save status</button></form>@endif</td>
   </tr>
   @if($u->role==='seller')
   @php $legacyStore=\Illuminate\Support\Facades\DB::table('seller_businesses')->where('user_id',$u->id)->first(); @endphp
   @if($legacyStore&&!in_array($legacyStore->category,config('registration.categories'),true))
   <tr><td colspan="9"><form method="post" action="/admin/users/{{$u->id}}/initial-category">@csrf<label>Assign permanent category for legacy store {{$legacyStore->name}} (previous: {{$legacyStore->category}})<select name="category" required><option value="">Choose carefully — permanent</option>@foreach(config('registration.categories') as $main)<option>{{$main}}</option>@endforeach</select></label><button class="btn">Assign main category</button></form></td></tr>
   @endif @endif
   @empty
   <tr><td colspan="8" class="empty">No accounts match your filters. Try another name or reset the filters.</td></tr>
   @endforelse
  </tbody>
 </table>
</div>
{{$users->links('components.pagination')}}
@endsection

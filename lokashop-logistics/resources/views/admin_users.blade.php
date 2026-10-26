@extends('dashboard')
@section('title', 'Logistics accounts')
@section('panel')
<div class="page-head"><div><h1>Logistics accounts</h1><p>Manage sorting centers, riders and drivers.</p></div></div>
<section class="ops-card">
<form method="get" action="/admin/users" class="ops-filter">
 <label>Search name or email<input name="search" value="{{ $search }}" maxlength="150" placeholder="Find an account"></label>
 <label>Account type<select name="role"><option value="">All types</option>@foreach(['sorting_center'=>'Sorting centers','rider'=>'Riders','driver'=>'Drivers'] as $value=>$label)<option value="{{$value}}" @selected($role===$value)>{{$label}}</option>@endforeach</select></label>
 <label>Account status<select name="state"><option value="">All except archived</option>@foreach(['active','suspended','banned','archived'] as $value)<option value="{{$value}}" @selected($state===$value)>{{ucfirst($value)}}</option>@endforeach</select></label>
 <label>Approval<select name="approval"><option value="">All approvals</option>@foreach(['approved','pending','unverified','rejected','pending_activation'] as $value)<option value="{{$value}}" @selected($approval===$value)>{{ucfirst(str_replace('_',' ',$value))}}</option>@endforeach</select></label>
 <div class="ops-actions"><button>Filter</button><a class="btn alt" href="/admin/users">Reset</a></div>
</form><p class="ops-muted">{{ $users->total() }} accounts found · 12 per page</p>
</section>
<div class="panel-card"><div class="p-body flush table-wrap"><table class="data">
<thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Approval</th><th>Status</th><th>Profile</th><th>Account management</th></tr></thead>
<tbody>
@forelse($users as $u)
<tr><td>{{ $u->name }}</td><td>{{ $u->email }}</td><td>{{ ($u->role==='rider'?ucfirst($u->courier_type):ucfirst(str_replace('_', ' ', $u->role))) }}</td><td>{{ $u->approval_status }}</td><td>{{ ucfirst($u->account_status) }}{{ !$u->active ? ' / Disabled' : '' }}</td>
<td><a class="admin-view-profile" href="{{url('/admin/users/'.$u->id).(request()->query()?'?'.http_build_query(request()->only(['search','role','state','approval','page'])):'')}}" aria-label="View profile for {{$u->name}}">View profile</a></td><td>@include('admin-account-action')</td></tr>
@empty
<tr><td colspan="7" class="empty">No accounts yet.</td></tr>
@endforelse
</tbody></table>{{ $users->links('components.pagination') }}</div></div>
<script src="{{asset('js/admin-account-actions.js')}}" defer></script>
@endsection

@extends('dashboard')
@section('title', 'Logistics accounts')
@section('panel')
<div class="page-head"><div><h1>Logistics accounts</h1><p>Manage sorting centers and riders.</p></div></div>
<div class="panel-card"><div class="p-body flush table-wrap"><table class="data">
<thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Approval</th><th>Status</th><th>Action</th></tr></thead>
<tbody>
@forelse($users as $u)
<tr><td>{{ $u->name }}</td><td>{{ $u->email }}</td><td>{{ ($u->role==='rider'?ucfirst($u->courier_type):ucfirst(str_replace('_', ' ', $u->role))) }}</td><td>{{ $u->approval_status }}</td><td>{{ $u->active ? 'Active' : 'Disabled' }}</td>
<td><form method="post" action="/admin/users/{{$u->id}}/status">@csrf
<select name="status" aria-label="Account action">@foreach(['active'=>'Active / restore','suspended'=>'Suspend','banned'=>'Ban','archived'=>'Archive'] as $value=>$label)<option value="{{$value}}" @selected(($u->account_status??'active')===$value)>{{$label}}</option>@endforeach</select>
<input name="reason" required minlength="5" maxlength="300" placeholder="Reason for change"><button class="btn sm">Save status</button></form></td></tr>
@empty
<tr><td colspan="6" class="empty">No accounts yet.</td></tr>
@endforelse
</tbody></table>{{ $users->links('components.pagination') }}</div></div>
@endsection

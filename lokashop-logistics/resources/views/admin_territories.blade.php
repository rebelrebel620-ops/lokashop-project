@extends('dashboard')
@section('title','Service territories')
@section('panel')
<div class="page-head"><div><h1>Service territories</h1><p>Assign one sorting center to each municipality and keep its registered address up to date.</p></div></div>
<div class="ops-metrics"><div class="ops-card"><small>Sorting centers</small><strong>{{$totalCenters}}</strong></div><div class="ops-card"><small>Territories assigned</small><strong>{{$assignedCenters}}</strong></div><div class="ops-card"><small>Need assignment</small><strong>{{$totalCenters-$assignedCenters}}</strong></div></div>
<div class="ops-workspace">
 <section class="ops-card"><h2>Sorting centers</h2><form method="get" class="ops-filter"><label>Search center, municipality or email<input name="q" value="{{request('q')}}" maxlength="150" placeholder="Find a sorting center"></label><div class="ops-actions"><button>Search</button><a class="btn alt" href="/ops/territories">Reset</a></div></form>
 <div class="ops-table"><table class="data"><thead><tr><th>Center</th><th>Service municipality</th><th>Territory</th></tr></thead><tbody>@forelse($centers as $c)<tr><td><b>{{$c->name}}</b><small class="ops-muted">{{$c->email}} · #{{$c->id}}</small></td><td>{{$c->city ?: 'Not set'}}<small class="ops-muted">{{$c->province}}</small></td><td><span class="ops-pill {{$c->municipality_key?'':'warning'}}">{{$c->municipality_key?'Assigned':'Needs assignment'}}</span></td></tr>@empty<tr><td colspan="3">No matching sorting centers.</td></tr>@endforelse</tbody></table></div>{{ $centers->links('components.pagination') }}</section>
 <section class="ops-card"><h2>Assign or update territory</h2><p class="ops-muted">Choose the center by name, select its registered address and explain the change. Existing territory and active-parcel checks still apply.</p>@include('tool-form',['form'=>$form])</section>
</div>
@endsection

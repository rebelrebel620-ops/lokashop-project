@extends('dashboard')
@section('title','Area')
@section('panel')
<div class="page-head">
  <div><h1>Area</h1><p>These barangay areas cover seller pickups and buyer deliveries. Assign local riders only; drivers use bulk trips between centers.</p></div>
</div>

<div class="design-card">
  <h2><x-icon name="plus" size="17"/> Add pickup / delivery area</h2>
  <form method="post" action="/center/areas">
    @csrf
    <div class="field-grid">
      <div><label>Area name</label><input name="name" placeholder="e.g. Poblacion East" required></div>
      <div><label>Region</label><input name="region" value="{{old('region')}}" required></div>
      <div><label>Province</label><input name="province" value="{{old('province',$registeredCenter->province)}}" required></div>
      <div><label>City / Municipality</label><input name="city" value="{{old('city',$registeredCenter->city)}}" required></div>
      <div><label>Barangay</label><input name="barangay" value="{{old('barangay')}}" required></div>
      <div>
        <label>Local rider — pickup and delivery</label>
        <select name="rider_id" required><option value="">Choose an approved local rider</option>
          @foreach($riders as $u)<option value="{{ $u->id }}" @selected((string)old('rider_id',request('rider_id'))===(string)$u->id)>{{ $u->name }}</option>@endforeach
        </select>
      </div>
    </div>
    @if($riders->isEmpty())<p>No approved local rider is available. Open <a href="/ops/riders?type=rider">Riders</a> to review your team first.</p>@endif
    <button @disabled($riders->isEmpty())><x-icon name="plus" size="16"/> Save area</button>
  </form>
</div>

<div class="panel-card">
  <div class="p-head"><h2>Your local coverage</h2></div>
  <div class="p-body flush">
    <div class="row-list">
      @forelse($areas as $a)
        <div class="row-item">
          <span class="ico"><x-icon name="map-pin"/></span>
          <div class="info"><b>{{ $a->name }}</b><small>{{ $a->barangay ?? 'Set a barangay for this legacy area' }}, {{ $a->city }}, {{ $a->province }}</small></div>
          <span class="badge blue">{{ $riders->firstWhere('id',$a->rider_id)->name ?? ('Rider #'.$a->rider_id) }}</span>
          <details class="area-manage"><summary>Manage area</summary><form method="post" action="/center/areas/{{$a->id}}">@csrf<input type="hidden" name="action" value="assign"><label>Assigned rider<select name="rider_id" required>@foreach($riders as $rider)<option value="{{$rider->id}}" @selected($rider->id==$a->rider_id)>{{$rider->name}}</option>@endforeach</select></label><button @disabled($riders->isEmpty())>Change rider</button></form><form method="post" action="/center/areas/{{$a->id}}" onsubmit="return confirm('Delete this area? Active parcel work must be finished first.')">@csrf<input type="hidden" name="action" value="delete"><button class="danger">Delete area</button></form></details>
        </div>
      @empty
        <div class="empty">No delivery areas yet — add one above.</div>
      @endforelse
    </div>
  </div>
</div>
@endsection

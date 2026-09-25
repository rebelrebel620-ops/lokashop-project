@extends('dashboard')
@section('title','Delivery areas')
@section('panel')
<div class="page-head">
  <div><h1>Delivery areas</h1><p>Group destinations by area and match each one with an approved rider.</p></div>
</div>

<div class="form-card">
  <h2><x-icon name="plus" size="17"/> Add delivery area</h2>
  <form method="post" action="/center/areas">
    @csrf
    <div class="field-grid">
      <div><label>Area name</label><input name="name" placeholder="e.g. Poblacion East" required></div>
      <div><label>City</label><input name="city" required></div>
      <div><label>Province</label><input name="province" required></div>
      <div>
        <label>Assigned rider</label>
        <select name="rider_id">
          @foreach($riders as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
        </select>
      </div>
    </div>
    <button><x-icon name="plus" size="16"/> Save area</button>
  </form>
</div>

<div class="panel-card">
  <div class="p-head"><h2>Delivery area queue</h2></div>
  <div class="p-body flush">
    <div class="row-list">
      @forelse($areas as $a)
        <div class="row-item">
          <span class="ico"><x-icon name="map-pin"/></span>
          <div class="info"><b>{{ $a->name }}</b><small>{{ $a->city }}, {{ $a->province }}</small></div>
          <span class="badge blue">Rider #{{ $a->rider_id }}</span>
        </div>
      @empty
        <div class="empty">No delivery areas yet — add one above.</div>
      @endforelse
    </div>
  </div>
</div>
@endsection

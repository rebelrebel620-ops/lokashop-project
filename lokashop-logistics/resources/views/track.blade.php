@extends('layout')
@section('title','Track parcel')
@section('body-class','logistics-track')
@section('content')
<a class="public-back" href="/">&larr; Back to home</a>
<div class="track-wrap">
  <div class="track-card">
    <span class="public-eyebrow">EVERY PARCEL, EVERY STEP</span><h1>Track your parcel</h1><p class="public-muted">Enter your tracking code to see the latest delivery updates.</p>
    <form action="/track">
      <label class="tracking-label" for="tracking-code">Tracking code</label><input id="tracking-code" name="code" value="{{ $code }}" placeholder="LKS-XXXXXXXXXXXX" required>
      <button><x-icon name="search" size="18"/> Track</button>
    </form>

    @if($code !== '' && !$parcel)
      <p class="error">No parcel found for that code.</p>
    @endif

    @if($parcel)
      <div style="margin-top:18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <span class="track-code">{{ $parcel->tracking_code }}</span>
        <x-badge :status="$parcel->status" />
      </div>
      <p style="color:var(--muted)"><x-icon name="map-pin" size="16"/> {{ $parcel->city }}, {{ $parcel->province }}</p>

      <ul class="steps-line">
        @foreach($events->reverse() as $e)
          <li><strong>{{ str_replace('_',' ',$e->to_status) }}</strong><br><small>{{ $e->created_at }}</small></li>
        @endforeach
      </ul>
    @endif
  </div>
</div>
@endsection

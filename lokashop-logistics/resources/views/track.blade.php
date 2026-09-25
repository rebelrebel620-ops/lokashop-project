@extends('layout')
@section('title','Track parcel')
@section('content')
<div class="track-wrap">
  <div class="track-card">
    <h1>Track your parcel</h1>
    <form action="/track">
      <input name="code" value="{{ $code }}" placeholder="LKS-XXXXXXXXXXXX" required>
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

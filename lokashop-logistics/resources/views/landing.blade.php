@extends('layout')

@section('hero')
<section class="hero">
  <div class="hero-in">
    <div class="hero-copy">
      <h1>Moving every order forward.</h1>
      <p>From seller pickup to sorting and doorstep delivery, LokaShop Logistics keeps every parcel tracked at each step.</p>
      <div class="cta-row">
        <form action="/track">
          <input name="code" placeholder="Enter tracking code, e.g. LKS-1024" required>
          <button class="lime">Track parcel</button>
        </form>
      </div>
      <a class="btn alt" href="/register" style="background:#ffffff1a;color:#fff;border-color:#ffffff33">
        <x-icon name="truck" size="18"/> Join as a rider
      </a>
    </div>
    <div class="hero-visual">
      <div class="panel">
        <div class="rider-chip">
          <span class="ico"><x-icon name="truck"/></span>
          <div><b>Out for delivery</b><br><small>LKS-1024 &middot; Santa Cruz, Laguna</small></div>
        </div>
        <div class="rider-chip">
          <span class="ico"><x-icon name="building"/></span>
          <div><b>Sorted at center</b><br><small>Routed to the right delivery area</small></div>
        </div>
        <div class="route-track">
          <span class="dot"></span><span class="ln"></span><span class="dot"></span><span class="ln"></span><span class="dot"></span>
        </div>
        <small style="opacity:.8">Pickup &rarr; Sorting center &rarr; Doorstep</small>
      </div>
    </div>
  </div>
  <div class="stat-strip">
    <div class="row">
      <div class="cell"><b>{{ $stats['delivered'] }}</b><span>Parcels delivered</span></div>
      <div class="cell"><b>{{ $stats['riders'] }}</b><span>Active riders</span></div>
      <div class="cell"><b>{{ $stats['areas'] }}</b><span>Delivery areas</span></div>
      <div class="cell"><b>{{ $stats['centers'] }}</b><span>Sorting centers</span></div>
    </div>
  </div>
</section>
@endsection

@section('content')

<section class="sec" style="margin-top:56px">
  <div class="icon-row">
    <div class="item"><span class="ico"><x-icon name="check-circle" size="20"/></span><div><b>Verified pickup</b><small>Parcels collected and checked</small></div></div>
    <div class="item"><span class="ico"><x-icon name="building" size="20"/></span><div><b>Sorting by destination</b><small>Routed to the right area</small></div></div>
    <div class="item"><span class="ico"><x-icon name="truck" size="20"/></span><div><b>Rider assignment</b><small>Matched with nearby riders</small></div></div>
    <div class="item"><span class="ico"><x-icon name="bell" size="20"/></span><div><b>Delivery updates</b><small>Real-time status until delivered</small></div></div>
  </div>
</section>

<section class="sec">
  <h2>How LokaShop delivery works</h2>
  <p class="sub">From pickup to delivery, in four simple steps.</p>
  <div class="step-grid">
    <div class="step-card"><span class="n">01</span><span class="ico"><x-icon name="box" size="18"/></span><h3>Seller prepares</h3><p>Packages and labels the order for pickup.</p></div>
    <div class="step-card"><span class="n">02</span><span class="ico"><x-icon name="truck" size="18"/></span><h3>Rider picks up</h3><p>A nearby rider collects the parcel from the seller.</p></div>
    <div class="step-card"><span class="n">03</span><span class="ico"><x-icon name="scan" size="18"/></span><h3>Center sorts</h3><p>Parcels are scanned and routed by destination.</p></div>
    <div class="step-card"><span class="n">04</span><span class="ico"><x-icon name="map-pin" size="18"/></span><h3>Rider delivers</h3><p>Parcel is delivered to the customer with proof.</p></div>
  </div>
</section>

<section class="sec">
  <h2>Join the network</h2>
  <div class="split">
    <div class="panel-card">
      <span class="ico-lg"><x-icon name="truck" size="24"/></span>
      <div>
        <h3>Become a rider</h3>
        <p>Earn per pickup and delivery. Register and get approved by a sorting center.</p>
        <a class="btn" href="/register">Apply as rider</a>
      </div>
    </div>
    <div class="panel-card">
      <span class="ico-lg"><x-icon name="building" size="24"/></span>
      <div>
        <h3>Run a sorting center</h3>
        <p>Manage delivery areas, verify pickups and assign riders.</p>
        <a class="btn alt" href="/register">Register center</a>
      </div>
    </div>
  </div>
</section>

<section class="band">
  <h2>Already a partner?</h2>
  <p>Log in to manage assignments, parcels and earnings.</p>
  <a class="btn lime lg" href="/login">Partner log in</a>
</section>

@endsection

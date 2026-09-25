@extends('dashboard')
@section('title','Account')
@section('panel')
<div class="page-head"><div><h1>My account</h1></div></div>

<div class="form-card" style="max-width:560px">
  <h2><x-icon name="gear" size="17"/> Account details</h2>
  <form method="post" action="/profile">
    @csrf
    <label>Name</label>
    <input name="name" value="{{ $user->name }}" required>
    <label>Phone</label>
    <input name="phone" value="{{ $user->phone }}" required>
    <p style="color:var(--muted)">{{ $user->email }} &middot; {{ ucfirst(str_replace('_',' ',$user->role)) }}</p>
    <button>Save account</button>
  </form>
</div>

<div class="panel-card">
  <div class="p-head"><h2><x-icon name="bell" size="17"/> Notifications</h2></div>
  <div class="p-body flush">
    <div class="row-list">
      @forelse($notifications as $n)
        <div class="row-item">
          <span class="ico"><x-icon name="bell" size="16"/></span>
          <div class="info"><b>{{ $n->body }}</b><small>{{ $n->created_at }}</small></div>
        </div>
      @empty
        <div class="empty">No notifications yet.</div>
      @endforelse
    </div>
  </div>
</div>
@endsection

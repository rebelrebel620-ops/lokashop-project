@extends('app')
@section('title','My Account - LokaShop')
@section('content')
<div class="page-head"><div><h1>My account</h1><p>Update your details and review your notifications.</p></div></div>

<div class="panel-grid">
 <div class="form-card">
  <h3>Account details</h3>
  <form method="post" action="/profile">@csrf
   <label>Full name<input name="name" value="{{$user->name}}" required></label>
   <label>Phone<input name="phone" value="{{$user->phone}}" required></label>
   <p><small>{{$user->email}} &middot; {{ucfirst(str_replace('_',' ',$user->role))}}</small></p>
   <button class="btn">Save account</button>
  </form>
 </div>
 <div class="section-card">
  <div class="shead"><h3>Notifications</h3></div>
  @forelse($notifications as $n)
  <div class="list-row"><div class="ic">&#128276;</div><div><b>{{$n->body}}</b><small>{{\Illuminate\Support\Carbon::parse($n->created_at)->diffForHumans()}}</small></div></div>
  @empty
  <div class="empty">Nothing new yet.</div>
  @endforelse
 </div>
</div>
@endsection

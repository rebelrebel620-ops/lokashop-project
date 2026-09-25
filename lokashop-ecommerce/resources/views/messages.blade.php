@extends('app')
@section('title','Messages - LokaShop')
@section('content')
<div class="page-head"><div><h1>Messages</h1><p>Talk directly with buyers, sellers and the LokaShop team.</p></div></div>

<div class="panel-grid">
 <div class="section-card">
  <div class="shead"><h3>Conversation</h3></div>
  @forelse($messages as $m)
  <div class="list-row"><div class="ic">&#128172;</div><div><b>User #{{$m->sender_id}} &rarr; User #{{$m->recipient_id}}</b><br>{{$m->body}}<br><small>{{\Illuminate\Support\Carbon::parse($m->created_at)->diffForHumans()}}</small></div></div>
  @empty
  <div class="empty">No messages yet.</div>
  @endforelse
 </div>
 <div class="form-card">
  <h3>Send a message</h3>
  <form action="/messages" method="post">@csrf
   <label>To<select name="recipient_id">@foreach($users as $u)<option value="{{$u->id}}">{{$u->name}} ({{$u->role}})</option>@endforeach</select></label>
   <label>Message<textarea name="body" required maxlength="2000"></textarea></label>
   <button class="btn" style="width:100%">Send</button>
  </form>
 </div>
</div>
@endsection

@extends('dashboard')
@section('title','Messages')
@section('panel')
<div class="page-head"><div><h1>Messages</h1><p>Coordinate with sellers, riders and sorting centers.</p></div></div>

<div class="msg-layout">
  <div class="form-card">
    <h2><x-icon name="send" size="17"/> Send message</h2>
    <form action="/messages" method="post">
      @csrf
      <label>Recipient</label>
      <select name="recipient_id">
        @foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }} ({{ $u->role }})</option>@endforeach
      </select>
      <label>Message</label>
      <textarea name="body" required maxlength="2000"></textarea>
      <button class="block">Send</button>
    </form>
  </div>

  <div class="msg-thread">
    @forelse($messages as $m)
      <div class="msg-bubble">
        <b>From user #{{ $m->sender_id }} to #{{ $m->recipient_id }}</b>
        <p>{{ $m->body }}</p>
        <small>{{ $m->created_at }}</small>
      </div>
    @empty
      <div class="empty">No messages yet.</div>
    @endforelse
  </div>
</div>
@endsection

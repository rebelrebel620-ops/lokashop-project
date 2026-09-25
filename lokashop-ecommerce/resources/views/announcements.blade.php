@extends('app')
@section('title','Announcements - Admin')
@section('content')
<div class="page-head"><div><h1>Announcements</h1><p>Publish platform-wide updates for buyers and sellers.</p></div></div>

<div class="form-card">
 <h3>Publish announcement</h3>
 <form method="post" action="/admin/announcements">@csrf
  <input name="title" required placeholder="Title">
  <textarea name="body" required placeholder="Announcement"></textarea>
  <button class="btn">Publish</button>
 </form>
</div>

@forelse($records as $row)
<div class="section-card"><h3>{{$row->title}}</h3><p style="margin:0">{{$row->body}}</p></div>
@empty
<div class="section-card empty">No announcements yet.</div>
@endforelse
@endsection

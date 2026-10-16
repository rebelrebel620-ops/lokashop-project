@extends('app')
@section('content')
<div class="page-head"><h1>Notifications</h1></div>
<div class="section-card"><form method="post" action="/tools/notifications/read">@csrf<button class="btn">Mark all as read</button></form>
@forelse($notifications as $n)<div class="list-row"><div><b>{{$n->body}}</b><small>{{$n->created_at}} · {{$n->read_at?'Read':'Unread'}}</small></div></div>@empty<p>No notifications yet.</p>@endforelse
</div>{{$notifications->links()}}
@endsection

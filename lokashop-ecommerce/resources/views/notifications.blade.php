@extends(config('app.site_kind')==='logistics'?'dashboard':'app')
@section('title','Notifications - LokaShop')
@section(config('app.site_kind')==='logistics'?'panel':'content')
<div class="page-head"><div><h1>Notifications</h1><p>Updates for your account, newest first.</p></div></div>
<section class="design-card section-card"><form method="post" action="/notifications/read">@csrf<button class="btn">Mark all as read</button></form>
@forelse($notifications as $notice)
<article style="padding:18px 0;border-bottom:1px solid #e0eade;display:flex;gap:16px;align-items:start;justify-content:space-between"><div><p style="margin:0 0 8px;overflow-wrap:anywhere">{{$notice->body}}</p><small>{{\Illuminate\Support\Carbon::parse($notice->created_at)->format('M j, Y · g:i A')}} · <strong>{{$notice->read_at?'Read':'Unread'}}</strong></small></div>@if(!$notice->read_at)<form method="post" action="/notifications/read">@csrf<input type="hidden" name="id" value="{{$notice->id}}"><button class="btn alt" style="white-space:nowrap">Mark read</button></form>@endif</article>
@empty<p>No notifications yet. New account and order updates will appear here.</p>@endforelse
</section>{{$notifications->links('components.pagination')}}
@endsection

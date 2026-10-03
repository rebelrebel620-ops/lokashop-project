@extends('dashboard')
@section('title','Partner applications')
@section('panel')
<div class="page-head">
  <div><h1>Partner applications</h1><p>Review sorting center and rider registrations.</p></div>
</div>

<div class="grid">
  @forelse($users as $u)
    <div class="card">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
        <span class="ico" style="width:44px;height:44px;border-radius:11px;background:var(--green-bg);color:var(--g-600);display:grid;place-items:center;flex:none"><x-icon name="user"/></span>
        <div>
          <h3 style="margin:0">{{ $u->name }}</h3>
          <small>{{ $u->email }} &middot; {{ ucfirst(str_replace('_', ' ', $u->role)) }}</small>
        </div>
      </div>
      @foreach($docs[$u->id] ?? [] as $d)
        <a href="/admin/documents/{{ $d->id }}" style="display:flex;align-items:center;gap:6px;font-size:.87rem;margin-bottom:4px">
          <x-icon name="clipboard" size="15"/> View {{ $d->kind }} document
        </a>
      @endforeach
      <form method="post" action="/admin/approvals/{{ $u->id }}" style="margin-top:10px">
        @csrf
        <button name="decision" value="approved" class="sm"><x-icon name="check" size="15"/> Approve</button>
        <button name="decision" value="rejected" class="sm danger">Reject</button>
      </form>
    </div>
  @empty
    <div class="empty">No pending applications.</div>
  @endforelse
</div>
{{ $users->links('components.pagination') }}
@endsection

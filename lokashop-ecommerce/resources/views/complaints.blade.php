@extends('app')
@section('title','Complaints - LokaShop')
@section('content')
<div class="page-head"><div><h1>Complaints</h1><p>@if(auth()->user()->role==='buyer')Report an issue with an order.@else Review and resolve buyer complaints.@endif</p></div></div>

@if(auth()->user()->role==='buyer')
<div class="form-card">
 <h3>Send a complaint</h3>
 <form method="post" action="/complaints">@csrf
  <label>Order ID<input type="number" name="order_id" required></label>
  <label>Description<textarea name="description" required></textarea></label>
  <button class="btn">Send complaint</button>
 </form>
</div>
@endif

@forelse($complaints as $c)
<div class="section-card">
 <div class="shead"><h3>Order #{{$c->order_id}}</h3><span class="badge {{$c->status==='resolved'?'green':'orange'}}">{{ucfirst($c->status)}}</span></div>
 <p>{{$c->description}}</p>
 @if($c->resolution)<p><b>Resolution:</b> {{$c->resolution}}</p>@endif
 @if(auth()->user()->role==='admin' && $c->status==='open')
 <form method="post" action="/complaints/{{$c->id}}">@csrf
  <textarea name="resolution" placeholder="Describe how this was resolved" required></textarea>
  <button class="btn">Resolve</button>
 </form>
 @endif
</div>
@empty
<div class="section-card empty">No complaints on file.</div>
@endforelse
@endsection

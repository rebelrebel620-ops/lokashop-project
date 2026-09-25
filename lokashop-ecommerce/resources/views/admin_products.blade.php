@extends('app')
@section('title','Product review - Admin')
@section('content')
<div class="page-head"><div><h1>Product review</h1><p>Approve or reject products awaiting compliance review.</p></div></div>

@forelse($products as $p)
<div class="section-card">
 <div class="shead"><h3>{{$p->name}}</h3><span class="badge orange">Awaiting review</span></div>
 <p><small>{{$p->description}}</small></p>
 <p><small>Seller #{{$p->seller_id}} &middot; ₱{{number_format($p->price,2)}} &middot; {{$p->stock}} in stock</small></p>
 <form method="post" action="/admin/products/{{$p->id}}">@csrf
  <button name="decision" value="approve" class="btn">Approve</button>
  <button name="decision" value="reject" class="btn danger">Reject</button>
 </form>
</div>
@empty
<div class="section-card empty">Nothing awaiting review.</div>
@endforelse
{{$products->links()}}
@endsection

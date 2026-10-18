@extends('app')
@section('content')<div class="page-head"><div><h1>{{auth()->user()->role==='admin'?'Platform and shipping':'Store'}} vouchers</h1><p>Create a discount code and choose when buyers can use it.</p></div></div>
<div class="form-card"><h2>Create voucher</h2>@include('voucher-fields',['voucher'=>null])</div>
@foreach($vouchers as $voucher)<section class="form-card"><h3>{{$voucher->code}} · {{$voucher->active?'Active':'Ended'}} · {{$voucher->type==='percent'?$voucher->value.'%':'₱'.number_format($voucher->value,2)}}</h3>@include('voucher-fields')
<form method="post" action="{{auth()->user()->role==='admin'?'/tools/platform-voucher/end':'/tools/voucher/end'}}">@csrf<input type="hidden" name="id" value="{{$voucher->id}}"><button class="btn alt">End voucher</button></form></section>@endforeach
@endsection
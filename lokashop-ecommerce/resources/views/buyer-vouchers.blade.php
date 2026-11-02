@extends('app')

@section('title','Vouchers - LokaShop')

@section('content')
<link rel="stylesheet" href="{{asset('css/vouchers.css')}}"><script src="{{asset('js/vouchers.js')}}" defer></script>
<div class="page-head"><div><span class="voucher-eyebrow">MORE SAVINGS ON EVERY ORDER</span><h1>Your LokaShop vouchers</h1><p>Claim a voucher, then select its code at checkout to enjoy your discount.</p></div><a class="btn alt" href="/cart">Go to cart</a></div>
<nav class="voucher-tabs" aria-label="Voucher types">
@foreach(['all'=>'All vouchers','lokashop'=>'LokaShop','shipping'=>'Shipping','store'=>'Stores','claimed'=>'Claimed'] as $key=>$label)<a href="{{url('/tools/vouchers').'?'.http_build_query(['tab'=>$key])}}" 
@if($tab===$key) aria-current="page" 
@endif>{{$label}}</a>
@endforeach</nav>
<div class="voucher-list">
@forelse($vouchers as $v)
@include('voucher-ticket',['v'=>$v,'manager'=>false,'claimed'=>in_array($v->id,$claims),'used'=>(int)$uses->get($v->id,0)])
@empty<div class="voucher-empty"><h2>No vouchers in this section yet</h2><p>Check another voucher category or come back for new offers.</p><a class="btn alt" href="/shop">Continue shopping</a></div>
@endforelse</div>
{{$vouchers->links('components.pagination')}}
<p class="voucher-help" data-copy-message aria-live="polite">Discounts depend on minimum spend, validity, and usage limits. Claiming does not automatically apply a voucher.</p>

@endsection

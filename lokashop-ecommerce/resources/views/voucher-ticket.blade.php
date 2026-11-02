
@php $kind=\App\Services\VoucherDisplay::kind($v);$label=\App\Services\VoucherDisplay::label($v);$state=\App\Services\VoucherDisplay::state($v,(int)($used??0)); 
@endphp
<article class="voucher-ticket voucher-{{$kind}}">
<div class="voucher-art">
@include('voucher-icon',['kind'=>$kind])<strong>{{$label}}</strong></div>
<div class="voucher-copy"><h2>{{\App\Services\VoucherDisplay::offer($v)}}</h2><p class="voucher-subtitle">
@if($v->cap)Up to ₱{{number_format($v->cap,2)}} discount
@elseif($kind==='shipping')Save on your delivery fee
@elseif($kind==='store'){{$v->store_name??'Selected store only'}}
@else
More savings on your order
@endif</p><p class="voucher-min">Min. spend ₱{{number_format($v->minimum_spend??0,2)}}</p><span class="voucher-scope">{{$kind==='shipping'?'Shipping discount':($kind==='store'?($v->store_name??'Selected store only'):'Platform-wide')}}</span><small class="voucher-code">Code: <b>{{$v->code}}</b></small></div>
<div class="voucher-controls">

@if($manager??false)<span class="voucher-state">{{$state}}{{$v->claimable?' · Claimable':' · Code-only'}}</span><a class="btn alt" href="{{url($admin?'/tools/platform':'/tools/vouchers').'?'.http_build_query(['edit'=>$v->id]).'#voucher-editor'}}">Edit voucher</a>

@elseif($state!=='Available')<button type="button" disabled>{{$state}}</button>

@elseif($claimed??false)<span class="voucher-state">Claimed</span><button type="button" data-copy-voucher="{{$v->code}}">Copy code</button>

@else<form method="post" action="/tools/claim">
@csrf<input type="hidden" name="voucher_id" value="{{$v->id}}"><button>Claim</button></form>
@endif
<div class="voucher-valid">{{$v->expires_at?'Valid until '.\Illuminate\Support\Carbon::parse($v->expires_at)->format('M j, Y'):'No expiry date'}}<details><summary>T&amp;C</summary><div class="voucher-terms"><p>{{$label}} · {{$v->code}}</p><p>{{$kind==='shipping'?'Applies to shipping fees only.':($kind==='store'?'Applies only to this store’s products.':'Applies to eligible merchandise across stores.')}}</p><p>Minimum merchandise spend: ₱{{number_format($v->minimum_spend??0,2)}}.</p>
@if($v->cap)<p>Maximum discount: ₱{{number_format($v->cap,2)}}.</p>
@endif<p>Up to {{$v->per_buyer_limit??1}} use(s) per buyer. {{$v->usage_limit!==null?'Limited to '.$v->usage_limit.' total redemptions.':'No total redemption limit.'}}</p>
@if($v->starts_at)<p>Starts {{\Illuminate\Support\Carbon::parse($v->starts_at)->format('M j, Y g:i A')}}.</p>
@endif 
@if($v->expires_at)<p>Expires {{\Illuminate\Support\Carbon::parse($v->expires_at)->format('M j, Y g:i A')}}.</p>
@endif<p>Claiming saves a voucher; it does not reserve stock or guarantee eligibility. Select the code at checkout.</p></div></details></div>
</div></article>

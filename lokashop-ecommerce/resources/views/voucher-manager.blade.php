@extends('app')

@section('title','Voucher manager - LokaShop')

@section('content')
<link rel="stylesheet" href="{{asset('css/vouchers.css')}}"><script src="{{asset('js/vouchers.js')}}" defer></script>
<div class="page-head"><div><span class="voucher-eyebrow">CREATE MORE REASONS TO SHOP</span><h1>{{$admin?'LokaShop & shipping vouchers':'Store vouchers'}}</h1><p>{{$admin?'Create platform-wide product savings or delivery discounts.':'Create discounts that buyers can use only in your store.'}}</p></div></div>
<div class="voucher-editor" id="voucher-editor"><section class="voucher-form-panel"><h2>{{$voucher?'Edit '.$voucher->code:'Create a voucher'}}</h2>
@if($locked)<p class="voucher-lock">Buyers have already claimed this offer. You can extend its expiry and increase total uses; its other terms are protected.</p>
@endif 
@include('voucher-fields')</section><aside class="voucher-preview-panel"><span class="voucher-eyebrow">LIVE PREVIEW</span><h2>What buyers will see</h2><div class="voucher-preview" data-voucher-preview><div class="voucher-art" data-preview-art>
@include('voucher-icon',['kind'=>$voucher?\App\Services\VoucherDisplay::kind($voucher):($admin?'lokashop':'store')])<strong data-preview-label>{{$admin?'LokaShop':'Store voucher'}}</strong></div><div class="voucher-copy"><h2 data-preview-offer>15% OFF</h2><p data-preview-cap>Save on your next order</p><p data-preview-min>Min. spend ₱0.00</p><span class="voucher-scope" data-preview-scope>{{$admin?'Platform-wide':'Selected store only'}}</span><small class="voucher-code" data-preview-code>Code: YOURCODE</small></div><button type="button" disabled>Claim</button></div><p class="voucher-help">Preview only. Actual eligibility is checked at checkout.</p></aside></div>
<div class="voucher-section-head"><h2>Your vouchers</h2><span>{{$vouchers->total()}} total</span>
@if($voucher)<a href="{{$admin?'/tools/platform':'/tools/vouchers'}}">Create another voucher</a>
@endif</div>
<div class="voucher-list">
@forelse($vouchers as $v)<section class="managed-voucher">
@include('voucher-ticket',['v'=>$v,'manager'=>true,'used'=>0])<div class="voucher-management-meta"><span>{{$v->used_count}} / {{$v->usage_limit??'Unlimited'}} redeemed</span>
@if($v->active)<form method="post" action="{{$admin?'/tools/platform-voucher/end':'/tools/voucher/end'}}">
@csrf<input type="hidden" name="id" value="{{$v->id}}"><button class="voucher-end" onclick="return confirm('End this voucher? Buyers will no longer be able to use it.')">End voucher</button></form>
@endif</div></section>
@empty<div class="voucher-empty"><h2>No vouchers yet</h2><p>Create your first offer using the form above.</p></div>
@endforelse</div>
{{$vouchers->links('components.pagination')}}

@foreach(['lokashop','shipping','store'] as $iconKind)<template data-preview-icon="{{$iconKind}}">
@include('voucher-icon',['kind'=>$iconKind])</template>
@endforeach

@endsection

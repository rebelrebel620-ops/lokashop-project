@extends('app')
@section('title','Sales report - LokaShop')
@section('content')
<link rel="stylesheet" href="{{asset('css/seller-business.css')}}">
<div class="page-head"><div><span class="business-eyebrow">YOUR STORE PERFORMANCE</span><h1>Sales report</h1><p>Search orders, check sales progress and export the records you need.</p></div><a class="btn alt" href="{{url('/seller/reports').'?'.http_build_query(array_merge(request()->except(['page','format']),['format'=>'pdf']))}}">Download filtered PDF</a></div>
<div class="business-stats"><article><small>Matching orders</small><strong>{{number_format($stats->orders)}}</strong><span>All statuses in this filter</span></article><article><small>Order value</small><strong>₱{{number_format($stats->order_value,2)}}</strong><span>Buyer totals, including shipping; not revenue</span></article><article><small>Delivered orders</small><strong>{{number_format($stats->delivered??0)}}</strong><span>Delivered or completed, excluding refunded</span></article><article><small>Completed sales</small><strong>₱{{number_format($stats->completed_sales,2)}}</strong><span>Merchandise less store discounts, before commission</span></article></div>
<section class="business-panel"><form method="get" action="/seller/reports" class="sales-filters"><label>Search orders<input name="q" value="{{$filters['q']??''}}" maxlength="150" placeholder="Order ID, tracking, buyer or product"></label><label>Status<select name="status"><option value="">All statuses</option>
@foreach($statuses as $status)
<option value="{{$status}}" @selected(($filters['status']??'')===$status)>{{ucfirst(strtolower(str_replace('_',' ',$status)))}}</option>
@endforeach
</select></label><label>Placed from<input name="from" type="date" value="{{$filters['from']??''}}"></label><label>Placed to<input name="to" type="date" value="{{$filters['to']??''}}"></label><div class="business-actions"><button class="btn">Apply filters</button><a class="btn alt" href="/seller/reports">Reset</a></div></form><p class="business-help">Dates filter when orders were placed. Summary cards and the PDF use all matching records, not just this page. Sales figures do not represent an available payout balance.</p></section>
<div class="sales-results-heading"><h2>Order records</h2><span>{{$orders->total()}} matching orders</span></div>
<div class="sales-order-list">
@forelse($orders as $o)
@php $lineItems=$items->get($o->id,collect());$first=$lineItems->first();$successful=in_array($o->status,['DELIVERED','COMPLETED'])&&!$o->refunded_at;$cancelled=in_array($o->status,['CANCELLED','REFUNDED','RETURN_RECEIVED']); @endphp
<article class="sales-order"><div class="sales-order-heading"><div><strong>Order #{{$o->id}}</strong><small>{{\Illuminate\Support\Carbon::parse($o->created_at)->format('M j, Y · g:i A')}} · {{$o->buyer_name??'Buyer'}}</small></div><span class="business-status {{$cancelled?'ended':($successful?'live':'scheduled')}}">{{ucfirst(strtolower(str_replace('_',' ',$o->status)))}}</span></div>
<div class="sales-order-main"><div class="business-product">
@if($first&&$first->image)
<img src="{{\App\Services\Support::imageUrl($first->image)}}" alt="{{$first->name}}" loading="lazy">
@else
<div class="business-photo-empty">No photo</div>
@endif
<div><h3>{{$first->name??'Order items'}}</h3><p>{{$lineItems->sum('quantity')}} unit(s) · {{$o->tracking_code??'Tracking pending'}}</p>
@if($lineItems->count()>1)
<details><summary>View all products</summary>
@foreach($lineItems as $item)
<p>{{$item->name}} × {{$item->quantity}}</p>
@endforeach
</details>
@endif
</div></div><dl class="sales-amounts"><div><dt>Merchandise</dt><dd>₱{{number_format($o->merchandise_total,2)}}</dd></div><div><dt>Store discounts</dt><dd>−₱{{number_format($o->store_discount,2)}}</dd></div><div><dt>Commission recorded</dt><dd>₱{{number_format($o->commission,2)}}</dd></div><div><dt>Shipping</dt><dd>₱{{number_format($o->shipping_fee,2)}}</dd></div><div><dt>Buyer order total</dt><dd class="sales-total">₱{{number_format($o->total,2)}}</dd></div></dl></div>
<div class="business-actions sales-order-actions">
@if($o->tracking_code)
<a class="btn alt" href="/track/{{rawurlencode($o->tracking_code)}}">View order details</a>
@endif
<a class="business-text-link" href="/seller/orders">Manage orders</a></div></article>
@empty
<section class="business-empty"><h2>No orders match these filters</h2><p>Try another search or clear the date/status filters.</p><a class="btn alt" href="/seller/reports">Reset filters</a></section>
@endforelse
</div>
{{$orders->links('components.pagination')}}
@endsection

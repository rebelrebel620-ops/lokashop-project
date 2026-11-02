@extends('app')
@section('title','Reports - Admin')
@section('content')
<link rel="stylesheet" href="{{asset('css/admin-workspace.css')}}">
<div class="page-head"><div><span class="admin-eyebrow">MARKETPLACE PERFORMANCE</span><h1>Order reports</h1><p>Review activity across stores and export the records matching your filters.</p></div><a class="btn alt" href="{{url('/admin/reports').'?'.http_build_query(array_merge(request()->except(['page','format']),['format'=>'pdf']))}}">Download filtered PDF</a></div>
<div class="admin-stats"><article><small>Matching orders</small><strong>{{number_format($stats->orders)}}</strong><span>All statuses in the current filter</span></article><article><small>Buyer order value</small><strong>₱{{number_format($stats->order_value,2)}}</strong><span>Includes shipping; not platform revenue</span></article><article><small>Delivered orders</small><strong>{{number_format($stats->delivered??0)}}</strong><span>Delivered/completed, excluding refunded</span></article><article><small>Recorded commission</small><strong>₱{{number_format($stats->recorded_commission,2)}}</strong><span>Completed, non-refunded orders; not cash received</span></article></div>
<section class="admin-panel"><form method="get" action="/admin/reports" class="admin-filter"><label>Search<input name="q" value="{{$filters['q']??''}}" maxlength="150" placeholder="Order, tracking, buyer, seller or product"></label><label>Seller<select name="seller"><option value="">All sellers</option>
@foreach($sellers as $seller)
<option value="{{$seller->id}}" @selected((string)($filters['seller']??'')===(string)$seller->id)>{{$seller->name}}</option>
@endforeach
</select></label><label>Status<select name="status"><option value="">All statuses</option>
@foreach($statuses as $state)
<option value="{{$state}}" @selected(($filters['status']??'')===$state)>{{ucfirst(strtolower(str_replace('_',' ',$state)))}}</option>
@endforeach
</select></label><label>Placed from<input name="from" type="date" value="{{$filters['from']??''}}"></label><label>Placed to<input name="to" type="date" value="{{$filters['to']??''}}"></label><div class="admin-actions"><button class="btn">Apply filters</button><a class="btn alt" href="/admin/reports">Reset</a></div></form><p class="admin-help">Dates use order placement time. Summaries and PDF export include every matching order, not just the current page.</p></section>
@if($stats->legacy_records)
<p class="admin-notice">{{$stats->legacy_records}} matching legacy record(s) have a zero merchandise total despite a positive order value. These amounts are shown as recorded and should be reviewed; historical orders have not been recalculated.</p>
@endif
<div class="admin-list-heading"><h2>Order records</h2><span>{{$orders->total()}} results</span></div>
<div class="admin-record-list">
@forelse($orders as $o)
<article class="admin-record"><div class="admin-record-heading"><div><h3>Order #{{$o->id}}</h3><small>{{\Illuminate\Support\Carbon::parse($o->created_at)->format('M j, Y · g:i A')}} · {{$o->tracking_code??'Tracking pending'}}</small></div><span class="admin-status {{in_array($o->status,['DELIVERED','COMPLETED'])?'positive':(in_array($o->status,['CANCELLED','REFUNDED'])?'muted':'progress')}}">{{ucfirst(strtolower(str_replace('_',' ',$o->status)))}}</span></div><div class="admin-order-content"><div><p class="admin-record-label">STORE &amp; PEOPLE</p><strong>{{$o->store_name?:($o->seller_name??'Unknown seller')}}</strong><p class="admin-help">Seller: {{$o->seller_name??'Not recorded'}}<br>Buyer: {{$o->buyer_name??'Not recorded'}}</p><a class="admin-text-link" href="/admin/users/{{$o->seller_id}}">View seller profile</a></div><dl class="admin-amounts"><div><dt>Merchandise</dt><dd>₱{{number_format($o->merchandise_total,2)}}</dd></div><div><dt>Store discounts</dt><dd>₱{{number_format($o->store_discount,2)}}</dd></div><div><dt>Commission recorded</dt><dd>₱{{number_format($o->commission,2)}}</dd></div><div><dt>Shipping</dt><dd>₱{{number_format($o->shipping_fee,2)}}</dd></div><div><dt>Buyer total</dt><dd class="admin-price">₱{{number_format($o->total,2)}}</dd></div></dl></div><details class="admin-details"><summary>View products &amp; discounts</summary><div class="admin-purchased-items">
@foreach($items->get($o->id,collect()) as $item)
<div>
@if($item->image)
<img src="{{\App\Services\Support::imageUrl($item->image)}}" alt="{{$item->name}}" loading="lazy">
@endif
<span>{{$item->name}} × {{$item->quantity}}</span><strong>₱{{number_format($item->unit_price*$item->quantity,2)}}</strong></div>
@endforeach
</div><p class="admin-help">LokaShop discount: ₱{{number_format($o->platform_discount??0,2)}} · Shipping discount: ₱{{number_format($o->shipping_discount??0,2)}}</p></details></article>
@empty
<section class="admin-empty"><h2>No orders match these filters</h2><p>Try another search, seller, date range or status.</p><a class="btn alt" href="/admin/reports">Reset filters</a></section>
@endforelse
</div>{{$orders->links('components.pagination')}}
@endsection

@extends('app')
@section('title','Dashboard - LokaShop')
@section('content')
@php $u = auth()->user(); @endphp

@if($u->role === 'buyer')
 <div class="page-head">
  <div><h1>Good day, {{$u->name}} &#9728;&#65039;</h1><p>Here is what is happening with your orders.</p></div>
 </div>
 <div class="stat-grid">
  <div class="stat-card"><div class="ic">&#128230;</div><div><div class="val">{{$stat_active}}</div><div class="lbl">Active orders</div></div></div>
  <div class="stat-card"><div class="ic">&#9989;</div><div><div class="val">{{$stat_delivered}}</div><div class="lbl">Delivered</div></div></div>
  <div class="stat-card"><div class="ic warn">&#127991;&#65039;</div><div><div class="val">{{$stat_vouchers}}</div><div class="lbl">Saved vouchers</div></div></div>
 </div>

 <div class="panel-grid">
  <div>
   <div class="section-card">
    <div class="shead"><h3>Track your order</h3><a href="/orders">View all orders &rarr;</a></div>
    @if($latest_order)
     <b>Order #{{$latest_order->id}}</b> &middot; <span class="badge green">{{ucfirst(strtolower(str_replace('_',' ',$latest_order->status)))}}</span>
     <p style="margin:6px 0 0"><small>Tracking code {{$latest_order->tracking_code}} &middot; Total ₱{{number_format($latest_order->total,2)}}</small></p>
     <div class="stepper">
      @foreach(['Placed','Preparing','Pickup','Sorting','Out for delivery','Delivered'] as $i => $label)
       <div class="sp {{ $latest_step > $i+1 ? 'done' : ($latest_step === $i+1 ? 'now' : '') }}"><div class="dot">{{$i+1}}</div><small>{{$label}}</small></div>
      @endforeach
     </div>
    @else
     <div class="empty">No active orders right now. <a href="/shop">Start shopping &rarr;</a></div>
    @endif
   </div>

   <div class="section-card">
    <div class="shead"><h3>Recommended for you</h3><a href="/shop">View all products &rarr;</a></div>
    <div class="grid">
     @forelse($recommended as $p)
     <div class="card">
      <div class="product" style="--h:{{($p->id*47)%360}}">{{mb_strtoupper(mb_substr($p->name,0,1))}}</div>
      @if($p->discount_percent>0)<span class="tag off">{{rtrim(rtrim(number_format($p->discount_percent,1),'0'),'.')}}% OFF</span>@endif
      <h3>{{$p->name}}</h3>
      <p class="price">₱{{number_format($p->price*(1-$p->discount_percent/100),2)}}@if($p->discount_percent>0)<span class="price strike">₱{{number_format($p->price,2)}}</span>@endif</p>
      <a class="btn sm" href="/product/{{$p->id}}">View</a>
     </div>
     @empty
     <p>No products yet.</p>
     @endforelse
    </div>
   </div>
  </div>

  <div>
   <div class="section-card">
    <div class="shead"><h3>Delivery address</h3><a href="/cart">Edit &rarr;</a></div>
    @if($address)
     <b>{{$address->recipient}}</b>
     <p style="margin:4px 0 0"><small>{{$address->street}}, {{$address->barangay}}, {{$address->city}}, {{$address->province}} {{$address->postal_code}}</small></p>
    @else
     <div class="empty">No saved address yet. <a href="/cart">Add one at checkout &rarr;</a></div>
    @endif
   </div>
   <div class="section-card">
    <div class="shead"><h3>Recent notifications</h3><a href="/profile">View all &rarr;</a></div>
    @forelse($notifications as $n)
    <div class="list-row"><div class="ic">&#128276;</div><div><b>{{$n->body}}</b><small>{{\Illuminate\Support\Carbon::parse($n->created_at)->diffForHumans()}}</small></div></div>
    @empty
    <div class="empty">Nothing new yet.</div>
    @endforelse
   </div>
   <div class="section-card">
    <div class="shead"><h3>Your vouchers</h3><a href="/cart">Use at checkout &rarr;</a></div>
    @forelse($vouchers as $v)
    <div class="voucher"><div><b>{{rtrim(rtrim(number_format($v->discount_percent,1),'0'),'.')}}% OFF</b><small>Code {{$v->code}} @if($v->expires_at) &middot; Valid until {{\Illuminate\Support\Carbon::parse($v->expires_at)->format('M j, Y')}} @endif</small></div><span class="badge green">{{$v->code}}</span></div>
    @empty
    <div class="empty">No active vouchers right now.</div>
    @endforelse
   </div>
  </div>
 </div>

@elseif($u->role === 'seller')
 <div class="page-head">
  <div><h1>Welcome back, {{$u->name}}</h1><p>Here is your store at a glance.</p></div>
 </div>
 <div class="stat-grid">
  <div class="stat-card"><div class="ic">&#128200;</div><div><div class="val">₱{{number_format($stat_sales_today,2)}}</div><div class="lbl">Sales today</div></div></div>
  <div class="stat-card"><div class="ic warn">&#128230;</div><div><div class="val">{{$stat_to_prepare}}</div><div class="lbl">Orders to prepare</div></div></div>
  <div class="stat-card"><div class="ic blue">&#128666;</div><div><div class="val">{{$stat_ready}}</div><div class="lbl">Ready for pickup</div></div></div>
  <div class="stat-card"><div class="ic">&#11088;</div><div><div class="val">{{$store_rating}}</div><div class="lbl">Store rating &middot; {{$store_reviews}} reviews</div></div></div>
 </div>

 <div class="panel-grid">
  <div>
   <div class="table-card">
    <div class="shead" style="padding:16px 18px 0"><div class="shead"><h3>Recent orders</h3><a href="/seller/orders">View all orders &rarr;</a></div></div>
    <table>
     <thead><tr><th>Order</th><th>Buyer</th><th>Items</th><th>Total</th><th>Status</th></tr></thead>
     <tbody>
      @forelse($recent_orders as $o)
      <tr><td>#{{$o->id}}</td><td>{{$o->buyer}}</td><td>{{$o->items}} item(s)</td><td>₱{{number_format($o->total,2)}}</td>
       <td><span class="badge {{ in_array($o->status,['DELIVERED','COMPLETED']) ? 'green' : (in_array($o->status,['PLACED','CONFIRMED','PREPARING']) ? 'orange' : 'blue') }}">{{ucfirst(strtolower(str_replace('_',' ',$o->status)))}}</span></td></tr>
      @empty
      <tr><td colspan="5" class="empty">No orders yet.</td></tr>
      @endforelse
     </tbody>
    </table>
   </div>
   <div class="section-card">
    <div class="shead"><h3>Sales performance &middot; last 7 days</h3></div>
    <div class="mini-chart">
     @php $max = max(1, $chart->max('value')); @endphp
     @foreach($chart as $c)
      <div class="bar" style="height:{{ max(4, ($c['value']/$max)*100) }}%"><span>₱{{number_format($c['value'],0)}}</span><em>{{$c['label']}}</em></div>
     @endforeach
    </div>
   </div>
  </div>
  <div>
   <div class="section-card">
    <div class="shead"><h3>Top products</h3><a href="/seller/reports">Sales report &rarr;</a></div>
    <ul class="top-products">
     @forelse($top_products as $i => $p)
     <li><div class="rank">{{$i+1}}</div><div style="flex:1"><b>{{$p->name}}</b><br><small>{{$p->sold}} sold</small></div><b>₱{{number_format($p->price,2)}}</b></li>
     @empty
     <div class="empty">No sales yet.</div>
     @endforelse
    </ul>
   </div>
   <div class="section-card">
    <div class="shead"><h3>Quick links</h3></div>
    <a class="btn alt" style="width:100%;text-align:center;margin:0 0 8px" href="/seller/products">Manage products</a>
    <a class="btn alt" style="width:100%;text-align:center;margin:0" href="/seller/reports">Sales &amp; commission</a>
   </div>
  </div>
 </div>

@elseif($u->role === 'admin')
 <div class="page-head">
  <div><h1>Platform overview</h1><p>Manage your marketplace with confidence.</p></div>
 </div>
 <div class="stat-grid">
  <div class="stat-card"><div class="ic">&#128202;</div><div><div class="val">₱{{number_format($stat_total_sales,2)}}</div><div class="lbl">Total sales</div><div class="trend {{$stat_trend<0?'down':''}}">{{$stat_trend>=0?'▲':'▼'}} {{abs($stat_trend)}}% vs previous 7 days</div></div></div>
  <div class="stat-card"><div class="ic">&#128176;</div><div><div class="val">₱{{number_format($stat_commission,2)}}</div><div class="lbl">10% commission</div></div></div>
  <div class="stat-card"><div class="ic warn">&#128101;</div><div><div class="val">{{$stat_pending}}</div><div class="lbl">Pending approvals</div></div></div>
  <div class="stat-card"><div class="ic red">&#9888;&#65039;</div><div><div class="val">{{$stat_complaints}}</div><div class="lbl">Open complaints</div></div></div>
 </div>

 <div class="panel-grid">
  <div>
   <div class="table-card">
    <div class="shead" style="padding:16px 18px 0"><h3>Registrations awaiting review</h3></div>
    <table>
     <thead><tr><th>Name</th><th>Type</th><th>Date submitted</th><th>Status</th><th>Actions</th></tr></thead>
     <tbody>
      @forelse($pending_users as $p)
      <tr><td>{{$p->name}}<br><small>{{$p->email}}</small></td><td><span class="badge blue">{{ucfirst(str_replace('_',' ',$p->role))}}</span></td><td>{{\Illuminate\Support\Carbon::parse($p->created_at)->format('M j, Y')}}</td><td><span class="badge orange">Pending</span></td><td><a class="btn sm" href="/admin/approvals">Review</a></td></tr>
      @empty
      <tr><td colspan="5" class="empty">Nothing waiting for review.</td></tr>
      @endforelse
     </tbody>
    </table>
   </div>
   <div class="section-card">
    <div class="shead"><h3>Sales and commission &middot; last 7 days</h3></div>
    <div class="mini-chart">
     @php $max = max(1, $chart->max('sales')); @endphp
     @foreach($chart as $c)
      <div class="bar" style="height:{{ max(4, ($c['sales']/$max)*100) }}%"><span>₱{{number_format($c['sales'],0)}}</span><em>{{$c['label']}}</em></div>
     @endforeach
    </div>
   </div>
  </div>
  <div>
   <div class="section-card">
    <div class="shead"><h3>Needs attention</h3></div>
    <div class="needs"><div class="ic o">{{$stat_pending_products}}</div><div><b>Seller products awaiting compliance review</b><br><a href="/admin/products">Review products &rarr;</a></div></div>
    <div class="needs"><div class="ic r">{{$stat_complaints}}</div><div><b>Unresolved buyer complaints</b><br><a href="/complaints">View complaints &rarr;</a></div></div>
    <div class="needs"><div class="ic g">&#128227;</div><div><b>System announcements</b><br><a href="/admin/announcements">Manage announcements &rarr;</a></div></div>
   </div>
   <div class="section-card">
    <div class="shead"><h3>Recent activity</h3></div>
    @forelse($activity as $a)
    <div class="list-row"><div class="ic">&#128203;</div><div><b>{{$a['label']}}</b><br><small>{{\Illuminate\Support\Carbon::parse($a['at'])->diffForHumans()}}</small></div></div>
    @empty
    <div class="empty">No recent activity.</div>
    @endforelse
   </div>
  </div>
 </div>

@else
 <div class="page-head"><div><h1>{{ucfirst(str_replace('_',' ',$u->role))}} dashboard</h1><p>Welcome, {{$u->name}}.</p></div></div>
 <div class="section-card">
  @foreach($links as $label=>$url)<a class="btn" href="{{$url}}">{{$label}}</a>@endforeach
 </div>
@endif
@endsection

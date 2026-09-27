<?php $__env->startSection('title','Dashboard - LokaShop'); ?>
<?php $__env->startSection('content'); ?>
<?php $u = auth()->user(); ?>

<?php if($u->role === 'buyer'): ?>
 <div class="page-head">
  <div><h1>Good day, <?php echo e($u->name); ?> &#9728;&#65039;</h1><p>Here is what is happening with your orders.</p></div>
 </div>
 <div class="stat-grid">
  <div class="stat-card"><div class="ic">&#128230;</div><div><div class="val"><?php echo e($stat_active); ?></div><div class="lbl">Active orders</div></div></div>
  <div class="stat-card"><div class="ic">&#9989;</div><div><div class="val"><?php echo e($stat_delivered); ?></div><div class="lbl">Delivered</div></div></div>
  <div class="stat-card"><div class="ic warn">&#127991;&#65039;</div><div><div class="val"><?php echo e($stat_vouchers); ?></div><div class="lbl">Saved vouchers</div></div></div>
 </div>

 <div class="panel-grid">
  <div>
   <div class="section-card">
    <div class="shead"><h3>Track your order</h3><a href="/orders">View all orders &rarr;</a></div>
    <?php if($latest_order): ?>
     <b>Order #<?php echo e($latest_order->id); ?></b> &middot; <span class="badge green"><?php echo e(ucfirst(strtolower(str_replace('_',' ',$latest_order->status)))); ?></span>
     <p style="margin:6px 0 0"><small>Tracking code <?php echo e($latest_order->tracking_code); ?> &middot; Total ₱<?php echo e(number_format($latest_order->total,2)); ?></small></p>
     <div class="stepper">
      <?php $__currentLoopData = ['Placed','Preparing','Pickup','Sorting','Out for delivery','Delivered']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
       <div class="sp <?php echo e($latest_step > $i+1 ? 'done' : ($latest_step === $i+1 ? 'now' : '')); ?>"><div class="dot"><?php echo e($i+1); ?></div><small><?php echo e($label); ?></small></div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
     </div>
    <?php else: ?>
     <div class="empty">No active orders right now. <a href="/shop">Start shopping &rarr;</a></div>
    <?php endif; ?>
   </div>

   <div class="section-card">
    <div class="shead"><h3>Recommended for you</h3><a href="/shop">View all products &rarr;</a></div>
    <div class="grid">
     <?php $__empty_1 = true; $__currentLoopData = $recommended; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
     <div class="card">
      <div class="product" style="--h:<?php echo e(($p->id*47)%360); ?>"><?php echo e(mb_strtoupper(mb_substr($p->name,0,1))); ?></div>
      <?php if($p->discount_percent>0): ?><span class="tag off"><?php echo e(rtrim(rtrim(number_format($p->discount_percent,1),'0'),'.')); ?>% OFF</span><?php endif; ?>
      <h3><?php echo e($p->name); ?></h3>
      <p class="price">₱<?php echo e(number_format($p->price*(1-$p->discount_percent/100),2)); ?><?php if($p->discount_percent>0): ?><span class="price strike">₱<?php echo e(number_format($p->price,2)); ?></span><?php endif; ?></p>
      <a class="btn sm" href="/product/<?php echo e($p->id); ?>">View</a>
     </div>
     <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
     <p>No products yet.</p>
     <?php endif; ?>
    </div>
   </div>
  </div>

  <div>
   <div class="section-card">
    <div class="shead"><h3>Delivery address</h3><a href="/cart">Edit &rarr;</a></div>
    <?php if($address): ?>
     <b><?php echo e($address->recipient); ?></b>
     <p style="margin:4px 0 0"><small><?php echo e($address->street); ?>, <?php echo e($address->barangay); ?>, <?php echo e($address->city); ?>, <?php echo e($address->province); ?> <?php echo e($address->postal_code); ?></small></p>
    <?php else: ?>
     <div class="empty">No saved address yet. <a href="/cart">Add one at checkout &rarr;</a></div>
    <?php endif; ?>
   </div>
   <div class="section-card">
    <div class="shead"><h3>Recent notifications</h3><a href="/profile">View all &rarr;</a></div>
    <?php $__empty_1 = true; $__currentLoopData = $notifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $n): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <div class="list-row"><div class="ic">&#128276;</div><div><b><?php echo e($n->body); ?></b><small><?php echo e(\Illuminate\Support\Carbon::parse($n->created_at)->diffForHumans()); ?></small></div></div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <div class="empty">Nothing new yet.</div>
    <?php endif; ?>
   </div>
   <div class="section-card">
    <div class="shead"><h3>Your vouchers</h3><a href="/cart">Use at checkout &rarr;</a></div>
    <?php $__empty_1 = true; $__currentLoopData = $vouchers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <div class="voucher"><div><b><?php echo e(rtrim(rtrim(number_format($v->discount_percent,1),'0'),'.')); ?>% OFF</b><small>Code <?php echo e($v->code); ?> <?php if($v->expires_at): ?> &middot; Valid until <?php echo e(\Illuminate\Support\Carbon::parse($v->expires_at)->format('M j, Y')); ?> <?php endif; ?></small></div><span class="badge green"><?php echo e($v->code); ?></span></div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <div class="empty">No active vouchers right now.</div>
    <?php endif; ?>
   </div>
  </div>
 </div>

<?php elseif($u->role === 'seller'): ?>
 <div class="page-head">
  <div><h1>Welcome back, <?php echo e($u->name); ?></h1><p>Here is your store at a glance.</p></div>
 </div>
 <div class="stat-grid">
  <div class="stat-card"><div class="ic">&#128200;</div><div><div class="val">₱<?php echo e(number_format($stat_sales_today,2)); ?></div><div class="lbl">Sales today</div></div></div>
  <div class="stat-card"><div class="ic warn">&#128230;</div><div><div class="val"><?php echo e($stat_to_prepare); ?></div><div class="lbl">Orders to prepare</div></div></div>
  <div class="stat-card"><div class="ic blue">&#128666;</div><div><div class="val"><?php echo e($stat_ready); ?></div><div class="lbl">Ready for pickup</div></div></div>
  <div class="stat-card"><div class="ic">&#11088;</div><div><div class="val"><?php echo e($store_rating); ?></div><div class="lbl">Store rating &middot; <?php echo e($store_reviews); ?> reviews</div></div></div>
 </div>

 <div class="panel-grid">
  <div>
   <div class="table-card">
    <div class="shead" style="padding:16px 18px 0"><div class="shead"><h3>Recent orders</h3><a href="/seller/orders">View all orders &rarr;</a></div></div>
    <table>
     <thead><tr><th>Order</th><th>Buyer</th><th>Items</th><th>Total</th><th>Status</th></tr></thead>
     <tbody>
      <?php $__empty_1 = true; $__currentLoopData = $recent_orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <tr><td>#<?php echo e($o->id); ?></td><td><?php echo e($o->buyer); ?></td><td><?php echo e($o->items); ?> item(s)</td><td>₱<?php echo e(number_format($o->total,2)); ?></td>
       <td><span class="badge <?php echo e(in_array($o->status,['DELIVERED','COMPLETED']) ? 'green' : (in_array($o->status,['PLACED','CONFIRMED','PREPARING']) ? 'orange' : 'blue')); ?>"><?php echo e(ucfirst(strtolower(str_replace('_',' ',$o->status)))); ?></span></td></tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <tr><td colspan="5" class="empty">No orders yet.</td></tr>
      <?php endif; ?>
     </tbody>
    </table>
   </div>
   <div class="section-card">
    <div class="shead"><h3>Sales performance &middot; last 7 days</h3></div>
    <div class="mini-chart">
     <?php $max = max(1, $chart->max('value')); ?>
     <?php $__currentLoopData = $chart; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div class="bar" style="height:<?php echo e(max(4, ($c['value']/$max)*100)); ?>%"><span>₱<?php echo e(number_format($c['value'],0)); ?></span><em><?php echo e($c['label']); ?></em></div>
     <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
   </div>
  </div>
  <div>
   <div class="section-card">
    <div class="shead"><h3>Top products</h3><a href="/seller/reports">Sales report &rarr;</a></div>
    <ul class="top-products">
     <?php $__empty_1 = true; $__currentLoopData = $top_products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
     <li><div class="rank"><?php echo e($i+1); ?></div><div style="flex:1"><b><?php echo e($p->name); ?></b><br><small><?php echo e($p->sold); ?> sold</small></div><b>₱<?php echo e(number_format($p->price,2)); ?></b></li>
     <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
     <div class="empty">No sales yet.</div>
     <?php endif; ?>
    </ul>
   </div>
   <div class="section-card">
    <div class="shead"><h3>Quick links</h3></div>
    <a class="btn alt" style="width:100%;text-align:center;margin:0 0 8px" href="/seller/products">Manage products</a>
    <a class="btn alt" style="width:100%;text-align:center;margin:0" href="/seller/reports">Sales &amp; commission</a>
   </div>
  </div>
 </div>

<?php elseif($u->role === 'admin'): ?>
 <div class="page-head">
  <div><h1>Platform overview</h1><p>Manage your marketplace with confidence.</p></div>
 </div>
 <div class="stat-grid">
  <div class="stat-card"><div class="ic">&#128202;</div><div><div class="val">₱<?php echo e(number_format($stat_total_sales,2)); ?></div><div class="lbl">Total sales</div><div class="trend <?php echo e($stat_trend<0?'down':''); ?>"><?php echo e($stat_trend>=0?'▲':'▼'); ?> <?php echo e(abs($stat_trend)); ?>% vs previous 7 days</div></div></div>
  <div class="stat-card"><div class="ic">&#128176;</div><div><div class="val">₱<?php echo e(number_format($stat_commission,2)); ?></div><div class="lbl">10% commission</div></div></div>
  <div class="stat-card"><div class="ic warn">&#128101;</div><div><div class="val"><?php echo e($stat_pending); ?></div><div class="lbl">Pending approvals</div></div></div>
  <div class="stat-card"><div class="ic red">&#9888;&#65039;</div><div><div class="val"><?php echo e($stat_complaints); ?></div><div class="lbl">Open complaints</div></div></div>
 </div>

 <div class="panel-grid">
  <div>
   <div class="table-card">
    <div class="shead" style="padding:16px 18px 0"><h3>Registrations awaiting review</h3></div>
    <table>
     <thead><tr><th>Name</th><th>Type</th><th>Date submitted</th><th>Status</th><th>Actions</th></tr></thead>
     <tbody>
      <?php $__empty_1 = true; $__currentLoopData = $pending_users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <tr><td><?php echo e($p->name); ?><br><small><?php echo e($p->email); ?></small></td><td><span class="badge blue"><?php echo e(ucfirst(str_replace('_',' ',$p->role))); ?></span></td><td><?php echo e(\Illuminate\Support\Carbon::parse($p->created_at)->format('M j, Y')); ?></td><td><span class="badge orange">Pending</span></td><td><a class="btn sm" href="/admin/approvals">Review</a></td></tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <tr><td colspan="5" class="empty">Nothing waiting for review.</td></tr>
      <?php endif; ?>
     </tbody>
    </table>
   </div>
   <div class="section-card">
    <div class="shead"><h3>Sales and commission &middot; last 7 days</h3></div>
    <div class="mini-chart">
     <?php $max = max(1, $chart->max('sales')); ?>
     <?php $__currentLoopData = $chart; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div class="bar" style="height:<?php echo e(max(4, ($c['sales']/$max)*100)); ?>%"><span>₱<?php echo e(number_format($c['sales'],0)); ?></span><em><?php echo e($c['label']); ?></em></div>
     <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
   </div>
  </div>
  <div>
   <div class="section-card">
    <div class="shead"><h3>Needs attention</h3></div>
    <div class="needs"><div class="ic o"><?php echo e($stat_pending_products); ?></div><div><b>Seller products awaiting compliance review</b><br><a href="/admin/products">Review products &rarr;</a></div></div>
    <div class="needs"><div class="ic r"><?php echo e($stat_complaints); ?></div><div><b>Unresolved buyer complaints</b><br><a href="/complaints">View complaints &rarr;</a></div></div>
    <div class="needs"><div class="ic g">&#128227;</div><div><b>System announcements</b><br><a href="/admin/announcements">Manage announcements &rarr;</a></div></div>
   </div>
   <div class="section-card">
    <div class="shead"><h3>Recent activity</h3></div>
    <?php $__empty_1 = true; $__currentLoopData = $activity; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <div class="list-row"><div class="ic">&#128203;</div><div><b><?php echo e($a['label']); ?></b><br><small><?php echo e(\Illuminate\Support\Carbon::parse($a['at'])->diffForHumans()); ?></small></div></div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <div class="empty">No recent activity.</div>
    <?php endif; ?>
   </div>
  </div>
 </div>

<?php else: ?>
 <div class="page-head"><div><h1><?php echo e(ucfirst(str_replace('_',' ',$u->role))); ?> dashboard</h1><p>Welcome, <?php echo e($u->name); ?>.</p></div></div>
 <div class="section-card">
  <?php $__currentLoopData = $links; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label=>$url): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a class="btn" href="<?php echo e($url); ?>"><?php echo e($label); ?></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
 </div>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\Documents\LokaShop-all-in-one-v1\lokashop-ecommerce\resources\views/dashboard.blade.php ENDPATH**/ ?>
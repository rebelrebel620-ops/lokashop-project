<!doctype html>
<title>Order <?php echo e($parcel->tracking_code); ?> · LokaShop</title>
<style>
body{font:16px/1.5 system-ui;background:#f3f6f4;margin:0;padding:24px}
.card{max-width:480px;margin:0 auto;background:#fff;border:2px solid #164e30;border-radius:10px;padding:24px}
.card img{width:64px}
h1{font-size:20px;margin:12px 0 2px}
h2{font-size:16px;margin:0 0 16px;color:#164e30}
.badge{display:inline-block;background:#e7f4ec;color:#164e30;font-weight:600;font-size:13px;padding:3px 10px;border-radius:20px;margin-bottom:16px}
table{width:100%;border-collapse:collapse;margin:12px 0}
td{padding:6px 0;border-bottom:1px solid #eee;vertical-align:top}
td.label{color:#666;width:40%}
.items td{border-bottom:1px dashed #eee}
.totals td{border:none;padding:2px 0}
.totals tr:last-child td{font-weight:700;font-size:17px;border-top:2px solid #164e30;padding-top:8px}
.section-title{font-weight:700;margin:18px 0 4px;font-size:14px;text-transform:uppercase;letter-spacing:.03em;color:#164e30}
</style>
<div class="card">
  <img src="/logo.jpg">
  <h1>LokaShop</h1>
  <h2><?php echo e($parcel->tracking_code); ?></h2>
  <span class="badge"><?php echo e(str_replace('_',' ',$order->status)); ?></span>

  <div class="section-title">Order</div>
  <table>
    <tr><td class="label">Order #</td><td><?php echo e($order->id); ?></td></tr>
    <tr><td class="label">Placed</td><td><?php echo e(\Illuminate\Support\Carbon::parse($order->created_at)->format('M j, Y g:ia')); ?></td></tr>
    <?php if($seller): ?><tr><td class="label">Sold by</td><td><?php echo e($seller->name); ?></td></tr><?php endif; ?>
  </table>

  <div class="section-title">Buyer</div>
  <table>
    <tr><td class="label">Name</td><td><?php echo e($buyer->name ?? $address->recipient); ?></td></tr>
    <tr><td class="label">Phone</td><td><?php echo e($address->phone); ?></td></tr>
    <tr><td class="label">Address</td><td><?php echo e($address->street); ?>, <?php echo e($address->barangay); ?>, <?php echo e($address->city); ?>, <?php echo e($address->province); ?> <?php echo e($address->postal_code); ?></td></tr>
  </table>

  <div class="section-title">Items</div>
  <table class="items">
    <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <tr>
      <td><?php echo e($item->name); ?> <span style="color:#888">× <?php echo e($item->quantity); ?></span></td>
      <td style="text-align:right">₱<?php echo e(number_format($item->unit_price * $item->quantity, 2)); ?></td>
    </tr>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </table>

  <table class="totals">
    <?php if($order->discount > 0): ?><tr><td class="label">Discount</td><td style="text-align:right">-₱<?php echo e(number_format($order->discount,2)); ?></td></tr><?php endif; ?>
    <tr><td>Total (buyer pays)</td><td style="text-align:right">₱<?php echo e(number_format($order->total,2)); ?></td></tr>
  </table>

  <?php if($payment): ?>
  <div class="section-title">Payment</div>
  <table>
    <tr><td class="label">Method</td><td><?php echo e(strtoupper($payment->method)); ?></td></tr>
    <tr><td class="label">Status</td><td><?php echo e(ucfirst($payment->status)); ?></td></tr>
  </table>
  <?php endif; ?>
</div>
<?php /**PATH C:\Users\Administrator\Documents\LokaShop-all-in-one-v1\lokashop-ecommerce\resources\views/track.blade.php ENDPATH**/ ?>
<?php $__env->startSection('title','Orders - Seller Center'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-head"><div><h1>Orders</h1><p>Confirm, pack and hand off orders for pickup.</p></div></div>

<?php $__empty_1 = true; $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<div class="section-card">
 <div class="shead">
  <div><h3>Order #<?php echo e($o->id); ?></h3><small>Parcel #<?php echo e($o->parcel_id); ?> &middot; Total ₱<?php echo e(number_format($o->total,2)); ?></small></div>
  <span class="badge <?php echo e(in_array($o->status,['DELIVERED','COMPLETED']) ? 'green' : (in_array($o->status,['PLACED','CONFIRMED','PREPARING']) ? 'orange' : 'blue')); ?>"><?php echo e(ucfirst(strtolower(str_replace('_',' ',$o->status)))); ?></span>
 </div>
 <div class="inline-form">
  <a class="btn sm alt" href="/seller/orders/<?php echo e($o->id); ?>/label" target="_blank">Print shipping label</a>
  <?php $__currentLoopData = ['PLACED'=>'CONFIRMED','CONFIRMED'=>'PREPARING','PREPARING'=>'READY_FOR_PICKUP']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $from=>$to): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
   <?php if($o->status===$from): ?>
   <form method="post" action="/seller/orders/<?php echo e($o->id); ?>/status"><?php echo csrf_field(); ?><input type="hidden" name="status" value="<?php echo e($to); ?>"><button class="btn sm">Mark as <?php echo e(ucfirst(strtolower(str_replace('_',' ',$to)))); ?></button></form>
   <?php endif; ?>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  <?php if($o->status==='PREPARING'): ?>
  <form method="post" action="/seller/orders/<?php echo e($o->id); ?>/pickup" class="inline-form"><?php echo csrf_field(); ?>
   <select name="rider_id"><?php $__currentLoopData = $riders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rider): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($rider->id); ?>"><?php echo e($rider->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select>
   <button class="btn sm alt">Request pickup</button>
  </form>
  <?php endif; ?>
 </div>
</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
<div class="section-card empty">No orders yet.</div>
<?php endif; ?>
<?php echo e($orders->links()); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\Documents\LokaShop-all-in-one-v1\lokashop-ecommerce\resources\views/seller_orders.blade.php ENDPATH**/ ?>
<?php $__env->startSection('title','My Orders - LokaShop'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-head"><div><h1>My orders</h1><p>Track every order from placement to delivery.</p></div></div>

<?php $__empty_1 = true; $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<div class="section-card">
 <div class="shead">
  <div><h3>Order #<?php echo e($o->id); ?></h3><small>Tracking code <?php echo e($o->tracking_code); ?></small></div>
  <span class="badge <?php echo e(in_array($o->status,['DELIVERED','COMPLETED']) ? 'green' : (in_array($o->status,['DELIVERY_FAILED','RETURNED']) ? 'red' : 'blue')); ?>"><?php echo e(ucfirst(strtolower(str_replace('_',' ',$o->status)))); ?></span>
 </div>
 <p style="margin:0"><b class="price" style="font-size:1rem">Total: ₱<?php echo e(number_format($o->total,2)); ?></b></p>
 <?php if($o->failure_reason): ?><p style="color:var(--red)"><small>Delivery issue: <?php echo e($o->failure_reason); ?></small></p><?php endif; ?>
 <?php if($o->status==='DELIVERED'): ?>
 <form method="post" action="/orders/<?php echo e($o->id); ?>/confirm"><?php echo csrf_field(); ?><button class="btn">Confirm received</button></form>
 <?php endif; ?>
 <?php if($o->status==='COMPLETED'): ?>
 <form method="post" action="/orders/<?php echo e($o->id); ?>/rating" class="inline-form" style="margin-top:10px;display:block"><?php echo csrf_field(); ?>
  <label>Your rating<select name="stars"><?php for($i=5;$i>=1;$i--): ?><option value="<?php echo e($i); ?>"><?php echo e($i); ?> stars</option><?php endfor; ?></select></label>
  <label>Feedback<textarea name="comment" placeholder="Tell other buyers about this seller"></textarea></label>
  <button class="btn">Save feedback</button>
 </form>
 <?php endif; ?>
</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
<div class="section-card empty">No orders yet. <a href="/shop">Start shopping &rarr;</a></div>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\Documents\LokaShop-all-in-one-v1\lokashop-ecommerce\resources\views/orders.blade.php ENDPATH**/ ?>
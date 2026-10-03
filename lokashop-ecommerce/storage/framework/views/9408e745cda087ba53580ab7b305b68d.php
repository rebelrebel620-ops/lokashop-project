<?php $__env->startSection('title','Complaints - LokaShop'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-head"><div><h1>Complaints</h1><p><?php if(auth()->user()->role==='buyer'): ?>Report an issue with an order.<?php else: ?> Review and resolve buyer complaints.<?php endif; ?></p></div></div>

<?php if(auth()->user()->role==='buyer'): ?>
<div class="form-card">
 <h3>Send a complaint</h3>
 <form method="post" action="/complaints"><?php echo csrf_field(); ?>
  <label>Order ID<input type="number" name="order_id" required></label>
  <label>Description<textarea name="description" required></textarea></label>
  <button class="btn">Send complaint</button>
 </form>
</div>
<?php endif; ?>

<?php $__empty_1 = true; $__currentLoopData = $complaints; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<div class="section-card">
 <div class="shead"><h3>Order #<?php echo e($c->order_id); ?></h3><span class="badge <?php echo e($c->status==='resolved'?'green':'orange'); ?>"><?php echo e(ucfirst($c->status)); ?></span></div>
 <p><?php echo e($c->description); ?></p>
 <?php if($c->resolution): ?><p><b>Resolution:</b> <?php echo e($c->resolution); ?></p><?php endif; ?>
 <?php if(auth()->user()->role==='admin' && $c->status==='open'): ?>
 <form method="post" action="/complaints/<?php echo e($c->id); ?>"><?php echo csrf_field(); ?>
  <textarea name="resolution" placeholder="Describe how this was resolved" required></textarea>
  <button class="btn">Resolve</button>
 </form>
 <?php endif; ?>
</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
<div class="section-card empty">No complaints on file.</div>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\Documents\LokaShop-all-in-one-v1\lokashop-ecommerce\resources\views/complaints.blade.php ENDPATH**/ ?>
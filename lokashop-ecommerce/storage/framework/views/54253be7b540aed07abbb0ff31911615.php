<?php $__env->startSection('title','Product review - Admin'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-head"><div><h1>Product review</h1><p>Approve or reject products awaiting compliance review.</p></div></div>

<?php $__empty_1 = true; $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<div class="section-card">
 <div class="shead"><h3><?php echo e($p->name); ?></h3><span class="badge orange">Awaiting review</span></div>
 <p><small><?php echo e($p->description); ?></small></p>
 <p><small>Seller #<?php echo e($p->seller_id); ?> &middot; ₱<?php echo e(number_format($p->price,2)); ?> &middot; <?php echo e($p->stock); ?> in stock</small></p>
 <form method="post" action="/admin/products/<?php echo e($p->id); ?>"><?php echo csrf_field(); ?>
  <button name="decision" value="approve" class="btn">Approve</button>
  <button name="decision" value="reject" class="btn danger">Reject</button>
 </form>
</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
<div class="section-card empty">Nothing awaiting review.</div>
<?php endif; ?>
<?php echo e($products->links()); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\Documents\LokaShop-all-in-one-v1\lokashop-ecommerce\resources\views/admin_products.blade.php ENDPATH**/ ?>
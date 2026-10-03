<?php $__env->startSection('title','Approvals - Admin'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-head"><div><h1>Pending registrations</h1><p>Review identity and permit documents before approving new accounts.</p></div></div>

<?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<div class="section-card">
 <div class="shead"><h3><?php echo e($u->name); ?></h3><span class="badge blue"><?php echo e(ucfirst(str_replace('_',' ',$u->role))); ?></span></div>
 <p><small><?php echo e($u->email); ?> &middot; <?php echo e($u->phone); ?></small></p>
 <div class="inline-form">
  <?php $__currentLoopData = $docs[$u->id]??[]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $doc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a class="btn sm alt" href="/admin/documents/<?php echo e($doc->id); ?>" target="_blank">View <?php echo e($doc->kind); ?> document</a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
 </div>
 <form method="post" action="/admin/approvals/<?php echo e($u->id); ?>" style="margin-top:8px"><?php echo csrf_field(); ?>
  <button name="decision" value="approved" class="btn">Approve</button>
  <button name="decision" value="rejected" class="btn danger">Reject</button>
 </form>
</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
<div class="section-card empty">Nothing waiting for review.</div>
<?php endif; ?>
<?php echo e($users->links()); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\Documents\LokaShop-all-in-one-v1\lokashop-ecommerce\resources\views/admin_approvals.blade.php ENDPATH**/ ?>
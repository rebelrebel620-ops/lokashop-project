<?php $__env->startSection('title','My Account - LokaShop'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-head"><div><h1>My account</h1><p>Update your details and review your notifications.</p></div></div>

<div class="panel-grid">
 <div class="form-card">
  <h3>Account details</h3>
  <form method="post" action="/profile"><?php echo csrf_field(); ?>
   <label>Full name<input name="name" value="<?php echo e($user->name); ?>" required></label>
   <label>Phone<input name="phone" value="<?php echo e($user->phone); ?>" required></label>
   <p><small><?php echo e($user->email); ?> &middot; <?php echo e(ucfirst(str_replace('_',' ',$user->role))); ?></small></p>
   <button class="btn">Save account</button>
  </form>
 </div>
 <div class="section-card">
  <div class="shead"><h3>Notifications</h3></div>
  <?php $__empty_1 = true; $__currentLoopData = $notifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $n): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
  <div class="list-row"><div class="ic">&#128276;</div><div><b><?php echo e($n->body); ?></b><small><?php echo e(\Illuminate\Support\Carbon::parse($n->created_at)->diffForHumans()); ?></small></div></div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
  <div class="empty">Nothing new yet.</div>
  <?php endif; ?>
 </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\Documents\LokaShop-all-in-one-v1\lokashop-ecommerce\resources\views/profile.blade.php ENDPATH**/ ?>
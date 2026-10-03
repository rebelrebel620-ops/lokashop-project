<?php $__env->startSection('title','Announcements - Admin'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-head"><div><h1>Announcements</h1><p>Publish platform-wide updates for buyers and sellers.</p></div></div>

<div class="form-card">
 <h3>Publish announcement</h3>
 <form method="post" action="/admin/announcements"><?php echo csrf_field(); ?>
  <input name="title" required placeholder="Title">
  <textarea name="body" required placeholder="Announcement"></textarea>
  <button class="btn">Publish</button>
 </form>
</div>

<?php $__empty_1 = true; $__currentLoopData = $records; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<div class="section-card"><h3><?php echo e($row->title); ?></h3><p style="margin:0"><?php echo e($row->body); ?></p></div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
<div class="section-card empty">No announcements yet.</div>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\Documents\LokaShop-all-in-one-v1\lokashop-ecommerce\resources\views/announcements.blade.php ENDPATH**/ ?>
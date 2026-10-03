<?php $__env->startSection('title','Messages - LokaShop'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-head"><div><h1>Messages</h1><p>Talk directly with buyers, sellers and the LokaShop team.</p></div></div>

<div class="panel-grid">
 <div class="section-card">
  <div class="shead"><h3>Conversation</h3></div>
  <?php $__empty_1 = true; $__currentLoopData = $messages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
  <div class="list-row"><div class="ic">&#128172;</div><div><b>User #<?php echo e($m->sender_id); ?> &rarr; User #<?php echo e($m->recipient_id); ?></b><br><?php echo e($m->body); ?><br><small><?php echo e(\Illuminate\Support\Carbon::parse($m->created_at)->diffForHumans()); ?></small></div></div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
  <div class="empty">No messages yet.</div>
  <?php endif; ?>
 </div>
 <div class="form-card">
  <h3>Send a message</h3>
  <form action="/messages" method="post"><?php echo csrf_field(); ?>
   <label>To<select name="recipient_id"><?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($u->id); ?>"><?php echo e($u->name); ?> (<?php echo e($u->role); ?>)</option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
   <label>Message<textarea name="body" required maxlength="2000"></textarea></label>
   <button class="btn" style="width:100%">Send</button>
  </form>
 </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\Documents\LokaShop-all-in-one-v1\lokashop-ecommerce\resources\views/messages.blade.php ENDPATH**/ ?>
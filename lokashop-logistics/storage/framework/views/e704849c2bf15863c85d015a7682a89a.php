<?php $__env->startSection('title', 'Logistics accounts'); ?>
<?php $__env->startSection('panel'); ?>
<div class="page-head"><div><h1>Logistics accounts</h1><p>Manage sorting centers and riders.</p></div></div>
<div class="panel-card"><div class="p-body flush table-wrap"><table class="data">
<thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Approval</th><th>Status</th><th>Action</th></tr></thead>
<tbody>
<?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<tr><td><?php echo e($u->name); ?></td><td><?php echo e($u->email); ?></td><td><?php echo e(ucfirst(str_replace('_', ' ', $u->role))); ?></td><td><?php echo e($u->approval_status); ?></td><td><?php echo e($u->active ? 'Active' : 'Disabled'); ?></td>
<td><form method="post" action="/admin/users/<?php echo e($u->id); ?>/toggle"><?php echo csrf_field(); ?><button class="sm"><?php echo e($u->active ? 'Disable' : 'Enable'); ?></button></form></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
<tr><td colspan="6" class="empty">No accounts yet.</td></tr>
<?php endif; ?>
</tbody></table><?php echo e($users->links('components.pagination')); ?></div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('dashboard', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\Documents\LokaShop-all-in-one-v1\lokashop-logistics\resources\views/admin_users.blade.php ENDPATH**/ ?>
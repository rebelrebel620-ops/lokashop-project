<?php $__env->startSection('title',$title.' - LokaShop'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-head"><div><h1><?php echo e($title); ?></h1></div></div>

<?php $rows = collect($records->items()); $cols = $rows->isNotEmpty() ? array_keys(array_diff_key((array)$rows->first(), array_flip(['password','remember_token']))) : []; ?>
<div class="table-card">
 <?php if($rows->isNotEmpty()): ?>
 <table>
  <thead><tr><?php $__currentLoopData = $cols; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><th><?php echo e(str_replace('_',' ',$c)); ?></th><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?> <?php if(isset($actions)): ?><th></th><?php endif; ?></tr></thead>
  <tbody>
   <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $record): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
   <tr>
    <?php $__currentLoopData = $cols; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
     <?php $value = $record->{$c} ?? null; ?>
     <td>
      <?php if($c==='status' || $c==='role'): ?><span class="badge <?php echo e(in_array($value,['approved','active','DELIVERED','COMPLETED']) ? 'green' : (in_array($value,['pending','open']) ? 'orange' : 'blue')); ?>"><?php echo e(is_scalar($value)?ucfirst(str_replace('_',' ',(string)$value)):'-'); ?></span>
      <?php elseif($c==='active'): ?><span class="badge <?php echo e($value?'green':'gray'); ?>"><?php echo e($value?'Active':'Inactive'); ?></span>
      <?php elseif($c==='total'||$c==='commission'): ?>₱<?php echo e(number_format((float)$value,2)); ?>

      <?php else: ?> <?php echo e(is_scalar($value)?$value:'-'); ?>

      <?php endif; ?>
     </td>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php if(isset($actions)): ?>
    <td><?php $__currentLoopData = $actions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $action): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><form method="post" action="<?php echo e($action['url']); ?>"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo e($record->id); ?>"><button class="btn sm"><?php echo e($action['label']); ?></button></form><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></td>
    <?php endif; ?>
   </tr>
   <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </tbody>
 </table>
 <?php else: ?>
 <div class="empty">No records yet.</div>
 <?php endif; ?>
</div>
<?php echo e($records->links()); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\Documents\LokaShop-all-in-one-v1\lokashop-ecommerce\resources\views/list.blade.php ENDPATH**/ ?>
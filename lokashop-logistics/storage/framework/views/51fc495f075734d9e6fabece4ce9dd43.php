<?php $__env->startSection('title', $title); ?>
<?php $__env->startSection('panel'); ?>
<div class="page-head"><div><h1><?php echo e($title); ?></h1></div></div>

<div class="panel-card">
  <div class="p-body flush table-wrap">
    <table class="data">
      <?php if($records->isNotEmpty()): ?>
        <thead>
          <tr>
            <?php $__currentLoopData = $records->first(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key=>$value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php if(is_scalar($value) && !in_array($key,['password','remember_token'])): ?><th><?php echo e($key); ?></th><?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php if(isset($actions)): ?><th></th><?php endif; ?>
          </tr>
        </thead>
      <?php endif; ?>
      <tbody>
      <?php $__empty_1 = true; $__currentLoopData = $records; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $record): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr>
          <?php $__currentLoopData = $record; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key=>$value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php if(is_scalar($value) && !in_array($key,['password','remember_token'])): ?><td><?php echo e($value); ?></td><?php endif; ?>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          <?php if(isset($actions)): ?>
            <td>
              <?php $__currentLoopData = $actions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $action): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <form class="inline" method="post" action="<?php echo e($action['url']); ?>"><?php echo csrf_field(); ?>
                  <input type="hidden" name="id" value="<?php echo e($record->id); ?>">
                  <button class="sm"><?php echo e($action['label']); ?></button>
                </form>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td class="empty">No records yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
    <?php echo e($records->links('components.pagination')); ?>

  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('dashboard', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\Documents\LokaShop-all-in-one-v1\lokashop-logistics\resources\views/list.blade.php ENDPATH**/ ?>
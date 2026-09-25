<?php if($paginator->hasPages()): ?>
<div class="pager">
  <a href="<?php echo e($paginator->previousPageUrl()); ?>" class="<?php echo e($paginator->onFirstPage() ? 'off' : ''); ?>">&larr;</a>
  <?php $last=$paginator->lastPage(); $cur=$paginator->currentPage(); ?>
  <?php for($p=max(1,$cur-2); $p<=min($last,$cur+2); $p++): ?>
    <?php if($p==$cur): ?><span class="current"><?php echo e($p); ?></span><?php else: ?><a href="<?php echo e($paginator->url($p)); ?>"><?php echo e($p); ?></a><?php endif; ?>
  <?php endfor; ?>
  <a href="<?php echo e($paginator->nextPageUrl()); ?>" class="<?php echo e($paginator->hasMorePages() ? '' : 'off'); ?>">&rarr;</a>
</div>
<?php endif; ?>
<?php /**PATH C:\Users\Administrator\Documents\LokaShop-all-in-one-v1\lokashop-logistics\resources\views/components/pagination.blade.php ENDPATH**/ ?>
<?php $__env->startSection('title',$p->name.' - LokaShop'); ?>
<?php $__env->startSection('content'); ?>
<div class="panel-grid">
 <div>
  <div class="card">
   <div class="product" style="--h:<?php echo e(($p->category_id*47)%360); ?>"><?php echo e(mb_strtoupper(mb_substr($p->name,0,1))); ?></div>
  </div>
 </div>
 <div class="card">
  <h1><?php echo e($p->name); ?></h1>
  <p><?php echo e($p->description); ?></p>
  <p class="price" style="font-size:1.6rem">₱<?php echo e(number_format($p->price*(1-$p->discount_percent/100),2)); ?>

   <?php if($p->discount_percent>0): ?><span class="price strike">₱<?php echo e(number_format($p->price,2)); ?></span> <span class="tag off"><?php echo e(rtrim(rtrim(number_format($p->discount_percent,1),'0'),'.')); ?>% OFF</span><?php endif; ?>
  </p>
  <small><?php echo e($p->stock); ?> in stock</small>
  <?php if(auth()->guard()->check()): ?>
   <?php if(auth()->user()->role==='buyer'): ?>
   <form method="post" action="/cart" style="margin-top:16px"><?php echo csrf_field(); ?>
    <input type="hidden" name="product_id" value="<?php echo e($p->id); ?>">
    <label>Variation<select name="variation_id"><option value="">Standard</option><?php $__currentLoopData = $variations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($v->id); ?>"><?php echo e($v->name); ?> (<?php echo e($v->stock); ?> available)</option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
    <label>Quantity<input type="number" min="1" name="quantity" value="1"></label>
    <button class="btn lg">Add to cart</button>
   </form>
   <?php endif; ?>
  <?php else: ?>
  <a class="btn lg" href="/login" style="margin-top:16px">Log in to order</a>
  <?php endif; ?>
 </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\Documents\LokaShop-all-in-one-v1\lokashop-ecommerce\resources\views/product.blade.php ENDPATH**/ ?>
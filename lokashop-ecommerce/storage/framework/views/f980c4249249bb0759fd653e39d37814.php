<?php $__env->startSection('title','Shop - LokaShop'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-head"><div><h1>Shop local with LokaShop</h1><p>Browse approved products from verified local sellers.</p></div></div>

<div class="card" style="margin-bottom:22px">
 <form method="get" action="/shop" class="form-row" style="align-items:end">
  <label>Search<input name="q" placeholder="Search products" value="<?php echo e(request('q')); ?>"></label>
  <label>Category<select name="category"><option value="">All categories</option><?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($c->id); ?>" <?php if(request('category')==$c->id): echo 'selected'; endif; ?>><?php echo e($c->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
  <div><button style="width:100%">Search</button></div>
 </form>
</div>

<div class="grid">
 <?php $__empty_1 = true; $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
 <div class="card">
  <div class="product" style="--h:<?php echo e(($p->category_id*47)%360); ?>"><?php echo e(mb_strtoupper(mb_substr($p->name,0,1))); ?></div>
  <span class="tag"><?php echo e($p->category); ?></span>
  <?php if($p->discount_percent>0): ?><span class="tag off"><?php echo e(rtrim(rtrim(number_format($p->discount_percent,1),'0'),'.')); ?>% OFF</span><?php endif; ?>
  <h3><?php echo e($p->name); ?></h3>
  <p class="price">₱<?php echo e(number_format($p->price*(1-$p->discount_percent/100),2)); ?><?php if($p->discount_percent>0): ?><span class="price strike">₱<?php echo e(number_format($p->price,2)); ?></span><?php endif; ?></p>
  <small><?php echo e($p->stock); ?> in stock</small><br>
  <a class="btn" href="/product/<?php echo e($p->id); ?>">View product</a>
 </div>
 <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
 <p>No products match your search.</p>
 <?php endif; ?>
</div>
<div style="margin-top:20px"><?php echo e($products->links()); ?></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\Documents\LokaShop-all-in-one-v1\lokashop-ecommerce\resources\views/shop.blade.php ENDPATH**/ ?>
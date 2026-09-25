<?php $__env->startSection('hero'); ?>
<section class="hero">
 <div class="hero-in">
  <div>
   <h1>Your everyday finds, delivered with care.</h1>
   <p>Shop trusted local sellers and track every order from checkout to your doorstep.</p>
   <form action="/shop" style="display:flex;gap:10px;max-width:520px;margin:22px 0 0">
    <input name="q" placeholder="Search products, e.g. reusable bag" style="margin:0;border:0;padding:14px 16px">
    <button style="margin:0;background:var(--lime);color:var(--dark)">Search</button>
   </form>
   <div class="btnrow">
    <a class="btn lg" href="/shop">Shop now &rarr;</a>
    <a class="btn lg alt" href="/register">Become a seller</a>
   </div>
   <div class="stats">
    <div><b><?php echo e($stats['products']); ?></b><span>Products</span></div>
    <div><b><?php echo e($stats['sellers']); ?></b><span>Verified sellers</span></div>
    <div><b><?php echo e($stats['orders']); ?></b><span>Orders delivered</span></div>
   </div>
  </div>
  <div class="hero-visual">
   <div class="vc"><div class="em">&#127911;</div>Electronics</div>
   <div class="vc"><div class="em">&#128092;</div>Fashion</div>
   <div class="vc"><div class="em">&#129530;</div>Beauty</div>
   <div class="vc wide"><span>&#128230; Support local sellers &middot; Go further together</span><span>&#128994;</span></div>
  </div>
 </div>
</section>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('content'); ?>
<?php if($notice): ?><div class="banner"><strong><?php echo e($notice->title); ?>:</strong> <?php echo e($notice->body); ?></div><?php endif; ?>

<section class="featbar" style="margin:-32px -20px 40px;border-radius:16px">
 <div class="in">
  <div class="f"><div class="ic">&#9989;</div><div><b>Verified sellers</b><small>Trusted local shops</small></div></div>
  <div class="f"><div class="ic">&#128274;</div><div><b>Secure checkout</b><small>Multiple payment methods</small></div></div>
  <div class="f"><div class="ic">&#128666;</div><div><b>Live order tracking</b><small>Real-time updates</small></div></div>
  <div class="f"><div class="ic">&#128205;</div><div><b>Local delivery</b><small>Supporting Philippine communities</small></div></div>
 </div>
</section>

<section class="sec">
 <h2>Shop by category</h2>
 <div class="cat-grid">
  <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <a class="cat-card" href="/shop?category=<?php echo e($c->id); ?>"><div class="ic"><?php echo e(['Electronics'=>'💻','Fashion'=>'👕','Home & Living'=>'🛋️','Beauty'=>'🧴','Groceries'=>'🛒'][$c->name] ?? '🏷️'); ?></div><?php echo e($c->name); ?></a>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  <a class="cat-card" href="/shop"><div class="ic">&#8734;</div>All products</a>
 </div>
</section>

<section class="sec">
 <h2>Trending picks</h2>
 <p class="sub">Fresh from local sellers &middot; approved products, updated live.</p>
 <div class="grid">
  <?php $__empty_1 = true; $__currentLoopData = $featured; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
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
  <p>No products yet. Check back soon.</p>
  <?php endif; ?>
 </div>
</section>

<section class="sec">
 <h2>From cart to doorstep</h2>
 <p class="sub">A simple and secure shopping experience, made for every Juan.</p>
 <div class="steps-row">
  <div class="st"><div class="ic">&#128722;</div><div><b>1. Checkout</b><small>Choose your items and secure payment</small></div></div>
  <div class="st"><div class="ic">&#128230;</div><div><b>2. Seller prepares</b><small>Your order is packed with care</small></div></div>
  <div class="st"><div class="ic">&#128666;</div><div><b>3. Out for delivery</b><small>Our local riders bring it to you</small></div></div>
  <div class="st"><div class="ic">&#128205;</div><div><b>4. Track your order</b><small>Get real-time updates to your doorstep</small></div></div>
 </div>
</section>

<section class="band">
 <h2>Sell your products on LokaShop</h2>
 <p>Register your business, list products and reach local buyers.</p>
 <a class="btn lime lg" href="/register">Become a seller</a>
</section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\Documents\LokaShop-all-in-one-v1\lokashop-ecommerce\resources\views/landing.blade.php ENDPATH**/ ?>
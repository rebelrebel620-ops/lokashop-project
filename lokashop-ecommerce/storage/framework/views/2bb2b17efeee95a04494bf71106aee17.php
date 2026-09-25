<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo $__env->yieldContent('title','LokaShop Marketplace'); ?></title>
<link rel="icon" href="/favicon.jpg">
<?php echo app('Illuminate\Foundation\Vite')('resources/css/app.css'); ?>
</head>
<body>
<div class="topribbon">&#128666; Free delivery on selected orders &middot; Shop local, live better.</div>
<header class="site">
 <a class="brand" href="/"><img src="/logo.jpg" alt="LokaShop logo">LokaShop<small>Shop Local. Live Better.</small></a>
 <form class="hsearch" action="/shop" method="get">
  <input name="q" placeholder="Search products, brands, or stores...">
  <button>Search</button>
 </form>
 <nav class="hnav">
  <a href="/shop">Shop</a>
  <?php if(auth()->guard()->check()): ?>
   <a href="/dashboard">Dashboard</a>
   <a href="/messages">Messages</a>
   <a href="/profile">Account</a>
   <form method="post" action="/logout"><?php echo csrf_field(); ?><button class="btn alt">Log out</button></form>
  <?php else: ?>
   <a href="/login">Log in</a>
   <a class="btn" href="/register">Sign up</a>
  <?php endif; ?>
 </nav>
</header>
<?php echo $__env->yieldContent('hero'); ?>
<div class="wrap">
 <?php if(session('success')): ?><p class="notice"><?php echo e(session('success')); ?></p><?php endif; ?>
 <?php if($errors->any()): ?><div class="error"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p><?php echo e($error); ?></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div><?php endif; ?>
 <?php echo $__env->yieldContent('content'); ?>
</div>
<footer>
 <div class="foot">
  <div><h4>LokaShop Marketplace</h4><small style="margin:0">Local products from local sellers, delivered to your door.</small></div>
  <div><h4>Quick links</h4><a href="/shop">Browse products</a><a href="/register">Sell on LokaShop</a><a href="/login">Log in</a></div>
 </div>
 <small>&copy; <?php echo e(date('Y')); ?> LokaShop. All rights reserved.</small>
</footer>
</body>
</html>
<?php /**PATH C:\Users\Administrator\Documents\LokaShop-all-in-one-v1\lokashop-ecommerce\resources\views/layout.blade.php ENDPATH**/ ?>
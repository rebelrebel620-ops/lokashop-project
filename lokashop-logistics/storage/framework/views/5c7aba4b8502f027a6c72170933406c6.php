<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo $__env->yieldContent('title','LokaShop Logistics'); ?></title>
<link rel="icon" href="/favicon.jpg">
<?php echo app('Illuminate\Foundation\Vite')('resources/css/app.css'); ?>
</head>
<body class="logi">
<header class="m-header">
  <a class="brand" href="/">
    <span class="logo">L</span>
    LokaShop <small>Logistics</small>
  </a>
  <nav class="m-nav">
    <a href="/">Home</a>
    <a href="/track">Track parcel</a>
    <?php if(auth()->guard()->check()): ?>
      <a href="/dashboard">Dashboard</a>
      <a href="/messages">Messages</a>
      <a href="/profile">Account</a>
      <form method="post" action="/logout"><input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>"><button class="alt sm">Log out</button></form>
    <?php else: ?>
      <a href="/login">Log in</a>
      <a class="btn sm" href="/register">Register</a>
    <?php endif; ?>
  </nav>
</header>

<?php echo $__env->yieldContent('hero'); ?>

<div class="wrap">
  <?php if(session('success')): ?><p class="notice"><?php echo e(session('success')); ?></p><?php endif; ?>
  <?php if($errors->any()): ?>
    <div class="error"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p><?php echo e($error); ?></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div>
  <?php endif; ?>
  <?php echo $__env->yieldContent('content'); ?>
</div>

<footer class="m-footer">
  <div class="foot">
    <div>
      <h4>LokaShop Logistics</h4>
      <small style="margin:0">Pickup, sorting and last-mile delivery for local sellers.</small>
    </div>
    <div>
      <h4>Quick links</h4>
      <a href="/track">Track a parcel</a>
      <a href="/register">Join as courier or center</a>
      <a href="/login">Partner log in</a>
    </div>
  </div>
  <small class="bottom">&copy; <?php echo e(date('Y')); ?> LokaShop. All rights reserved.</small>
</footer>
</body>
</html>
<?php /**PATH C:\Users\Administrator\Documents\LokaShop-all-in-one-v1\lokashop-logistics\resources\views/layout.blade.php ENDPATH**/ ?>
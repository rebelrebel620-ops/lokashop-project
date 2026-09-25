<?php $__env->startSection('title', ($register ? 'Register' : 'Log in') . ' - LokaShop'); ?>
<?php $__env->startSection('content'); ?>
<div class="card auth">
 <img src="/logo.jpg" width="64" alt="LokaShop" style="border-radius:14px">
 <h1><?php echo e($register ? 'Create your account' : 'Welcome back'); ?></h1>
 <p class="sub" style="margin-top:-10px"><?php echo e($register ? 'Register as a buyer or seller to get started.' : 'Log in to continue to LokaShop.'); ?></p>
 <form action="<?php echo e($register ? '/register' : '/login'); ?>" method="post" enctype="multipart/form-data"><?php echo csrf_field(); ?>
  <?php if($register): ?>
  <label>I am a<select name="role"><?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($role); ?>"><?php echo e(ucfirst(str_replace('_',' ',$role))); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
  <label>Full name<input name="name" required value="<?php echo e(old('name')); ?>"></label>
  <label>Phone<input name="phone" required value="<?php echo e(old('phone')); ?>"></label>
  <label>Business / center name (if applicable)<input name="business_name"></label>
  <label>Vehicle and plate (rider)<input name="vehicle"></label>
  <label>Identification document<input type="file" name="id_document" required></label>
  <label>Permit / OR-CR (seller, center, rider)<input type="file" name="permit_document"></label>
  <?php endif; ?>
  <label>Email<input type="email" name="email" required value="<?php echo e(old('email')); ?>"></label>
  <label>Password<input type="password" name="password" required></label>
  <?php if($register): ?>
  <label>Confirm password<input type="password" name="password_confirmation" required></label>
  <?php endif; ?>
  <button class="btn lg" style="width:100%"><?php echo e($register ? 'Submit for approval' : 'Log in'); ?></button>
 </form>
 <p style="text-align:center;margin-top:14px">
  <?php if($register): ?> Already have an account? <a href="/login">Log in</a>
  <?php else: ?> New here? <a href="/register">Create an account</a> <?php endif; ?>
 </p>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\Documents\LokaShop-all-in-one-v1\lokashop-ecommerce\resources\views/auth.blade.php ENDPATH**/ ?>
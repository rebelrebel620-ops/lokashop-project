<?php $__env->startSection('title', $register ? 'Register' : 'Log in'); ?>
<?php $__env->startSection('content'); ?>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="logo">L</div>
    <h1><?php echo e($register ? 'Create your partner account' : 'Welcome back'); ?></h1>
    <p class="switch">
      <?php echo e($register ? 'Register as a rider or sorting center.' : 'Log in to manage pickups, parcels and deliveries.'); ?>

    </p>

    <form action="<?php echo e($register ? '/register' : '/login'); ?>" method="post" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <?php if($register): ?>
        <label>Role</label>
        <select name="role">
          <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($role); ?>"><?php echo e(ucfirst(str_replace('_',' ',$role))); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>

        <div class="field-grid">
          <div><label>Full name</label><input name="name" required value="<?php echo e(old('name')); ?>"></div>
          <div><label>Phone</label><input name="phone" required value="<?php echo e(old('phone')); ?>"></div>
        </div>

        <label>Business / center name (if applicable)</label>
        <input name="business_name">

        <label>Vehicle and plate (rider)</label>
        <input name="vehicle">

        <label>Identification document</label>
        <input type="file" name="id_document" required>

        <label>Permit / OR-CR (seller, center, rider)</label>
        <input type="file" name="permit_document">
      <?php endif; ?>

      <label>Email</label>
      <input type="email" name="email" required value="<?php echo e(old('email')); ?>">

      <label>Password</label>
      <input type="password" name="password" required>

      <?php if($register): ?>
        <label>Confirm password</label>
        <input type="password" name="password_confirmation" required>
      <?php endif; ?>

      <button class="block"><?php echo e($register ? 'Submit for approval' : 'Log in'); ?></button>
    </form>

    <p class="switch">
      <?php if($register): ?>
        Already have an account? <a href="/login">Log in</a>
      <?php else: ?>
        Need a partner account? <a href="/register">Register here</a>
      <?php endif; ?>
    </p>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\Documents\LokaShop-all-in-one-v1\lokashop-logistics\resources\views/auth.blade.php ENDPATH**/ ?>
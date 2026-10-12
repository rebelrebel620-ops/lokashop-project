<?php $__env->startSection('title', ($register ? 'Register' : 'Log in') . ' - LokaShop'); ?>
<?php $__env->startSection('content'); ?>
<style>.registration-form{max-width:700px;margin:24px auto}.registration-form label{display:block;margin:12px 0}.field-error{color:#a11;display:block;font-size:14px}.registration-form fieldset{border:1px solid #ddd;border-radius:12px;margin:18px 0;padding:16px}.registration-form input[type=checkbox]{width:auto}.password-row{display:flex;gap:8px;align-items:center}.password-row input{flex:1;min-width:0}.password-row button{width:auto}</style>
<div class="card registration-form">
 <img src="/logo.jpg" width="64" alt="LokaShop" style="border-radius:14px">
 <h1><?php echo e($register ? 'Create your account' : 'Welcome back'); ?></h1>
 <p><?php echo e($register ? 'Verify your email to activate a buyer account. Sellers also need admin approval.' : 'Log in to continue to LokaShop.'); ?></p>
 <form id="account-form" action="<?php echo e($register ? '/register' : '/login'); ?>" method="post" enctype="multipart/form-data"><?php echo csrf_field(); ?>
  <?php if($register): ?>
  <label>I am a<select name="role" id="account-role"><?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($role); ?>" <?php if(old('role','buyer')===$role): echo 'selected'; endif; ?>><?php echo e(ucfirst($role)); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select><?php $__errorArgs = ['role'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></label>
  <?php $__currentLoopData = ['first_name'=>'First name','last_name'=>'Last name']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <label><?php echo e($label); ?><input name="<?php echo e($field); ?>" required minlength="2" maxlength="50" value="<?php echo e(old($field)); ?>" autocomplete="<?php echo e($field==='first_name'?'given-name':'family-name'); ?>"><?php $__errorArgs = [$field];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></label>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  <label>Mobile number<input type="tel" name="phone" required maxlength="13" pattern="(09[0-9]{9}|\+639[0-9]{9})" placeholder="09171234567 or +639171234567" value="<?php echo e(old('phone')); ?>"><?php $__errorArgs = ['phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></label>
  <div data-role="buyer"><label>Date of birth<input type="date" name="date_of_birth" required max="<?php echo e(now()->subYears(18)->toDateString()); ?>" value="<?php echo e(old('date_of_birth')); ?>"><?php $__errorArgs = ['date_of_birth'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></label><small>You must be at least 18 years old.</small></div>
  <fieldset data-role="seller"><legend>Shop information</legend>
   <label>Shop name<input name="business_name" required minlength="3" maxlength="30" value="<?php echo e(old('business_name')); ?>"><?php $__errorArgs = ['business_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></label>
   <label>Shop category<select name="shop_category" required><option value="">Choose a category</option><?php $__currentLoopData = config('registration.categories'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option <?php if(old('shop_category')===$category): echo 'selected'; endif; ?>><?php echo e($category); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select><?php $__errorArgs = ['shop_category'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></label>
   <label>Seller type<select name="seller_type" id="seller-type" required><?php $__currentLoopData = ['Individual','Sole Proprietorship','Corporation']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option <?php if(old('seller_type')===$type): echo 'selected'; endif; ?>><?php echo e($type); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select><?php $__errorArgs = ['seller_type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></label>
   <label>TIN<input name="tin" required maxlength="15" pattern="[0-9]{3}-[0-9]{3}-[0-9]{3}-[0-9]{3}" placeholder="000-000-000-000" value="<?php echo e(old('tin')); ?>"><?php $__errorArgs = ['tin'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></label>
   <?php $__currentLoopData = ['id_document'=>'Government-issued ID','dti_document'=>'DTI certificate','sec_document'=>'SEC certificate','bir_document'=>'BIR Form 2303']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
   <label data-document="<?php echo e($field); ?>"><?php echo e($label); ?><input type="file" name="<?php echo e($field); ?>" accept=".jpg,.jpeg,.png,.pdf"><?php $__errorArgs = [$field];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></label>
   <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
   <small>JPG, PNG or PDF. Maximum 5 MB per file. Documents depend on seller type.</small>
  </fieldset>
  <fieldset><legend>Address</legend>
   <?php $__currentLoopData = ['region'=>'Region','province'=>'Province','city'=>'City / Municipality','barangay'=>'Barangay','street'=>'Street address','postal_code'=>'Postal code']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
   <label><?php echo e($label); ?><input name="<?php echo e($field); ?>" required maxlength="<?php echo e($field==='street'?200:($field==='postal_code'?4:120)); ?>" <?php if($field==='postal_code'): ?> inputmode="numeric" pattern="[0-9]{4}" <?php endif; ?> value="<?php echo e(old($field)); ?>"><?php $__errorArgs = [$field];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></label>
   <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </fieldset>
  <?php endif; ?>
  <label>Email<input type="email" name="email" required maxlength="190" value="<?php echo e(old('email')); ?>" autocomplete="email"><?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></label>
  <?php $__currentLoopData = $register?['password'=>'Password','password_confirmation'=>'Confirm password']:['password'=>'Password']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <label><?php echo e($label); ?><span class="password-row"><input id="<?php echo e($field); ?>" type="password" name="<?php echo e($field); ?>" required <?php if($register): ?> minlength="8" autocomplete="new-password" <?php else: ?> autocomplete="current-password" <?php endif; ?>><button type="button" class="btn alt" data-password="<?php echo e($field); ?>" aria-label="Show <?php echo e($label); ?>" aria-pressed="false">Show</button></span><?php $__errorArgs = [$field];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></label>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  <?php if($register): ?>
  <small>Use at least 8 characters, including uppercase and lowercase letters, a number and a special character.</small>
  <label><input type="checkbox" name="terms" value="1" required <?php if(old('terms')): echo 'checked'; endif; ?>> I accept the <a href="/terms" target="_blank" rel="noopener">Terms and Privacy Policy</a>.<?php $__errorArgs = ['terms'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></label>
  <?php endif; ?>
  <button id="submit-account" class="btn lg" style="width:100%"><?php echo e($register ? 'Create account' : 'Log in'); ?></button>
 </form>
 <p><?php if($register): ?> Already have an account? <a href="/login">Log in</a><?php else: ?> New here? <a href="/register">Create an account</a><?php endif; ?></p>
</div>
<script>
const role=document.getElementById('account-role'),type=document.getElementById('seller-type');
function updateFields(){
 if(!role)return;
 document.querySelectorAll('[data-role]').forEach(section=>{
  const enabled=section.dataset.role===role.value;section.hidden=!enabled;
  section.querySelectorAll('input,select').forEach(input=>input.disabled=!enabled);
 });
 document.querySelectorAll('[data-document]').forEach(label=>{
  const field=label.dataset.document;
  const needed=field==='id_document'||(field==='dti_document'&&type.value==='Sole Proprietorship')||(field==='sec_document'&&type.value==='Corporation')||(field==='bir_document'&&type.value!=='Individual');
  label.hidden=!needed;const input=label.querySelector('input');input.disabled=role.value!=='seller'||!needed;input.required=role.value==='seller'&&needed;
 });
}
role?.addEventListener('change',updateFields);type?.addEventListener('change',updateFields);updateFields();
document.querySelectorAll('[data-password]').forEach(button=>button.addEventListener('click',()=>{
 const input=document.getElementById(button.dataset.password),show=input.type==='password';input.type=show?'text':'password';button.textContent=show?'Hide':'Show';button.setAttribute('aria-pressed',String(show));
}));
document.getElementById('account-form').addEventListener('submit',()=>{
 const button=document.getElementById('submit-account');button.disabled=true;button.textContent='Please wait…';
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\Documents\LokaShop-all-in-one-v1\lokashop-ecommerce\resources\views/auth.blade.php ENDPATH**/ ?>
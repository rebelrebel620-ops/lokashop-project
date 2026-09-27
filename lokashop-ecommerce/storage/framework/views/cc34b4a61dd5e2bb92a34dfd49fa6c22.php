<?php $__env->startSection('title','My Cart - LokaShop'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-head"><div><h1>My cart</h1><p>Items from different sellers become separate orders at checkout.</p></div></div>

<div class="panel-grid">
 <div>
  <div class="section-card">
   <div class="shead"><h3>Items</h3></div>
   <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $x): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
   <div class="order-row">
    <div class="thumb"><?php echo e(mb_strtoupper(mb_substr($x->name,0,1))); ?></div>
    <div class="grow"><b><?php echo e($x->name); ?> <?php echo e($x->variation); ?></b><small>Quantity: <?php echo e($x->quantity); ?></small></div>
    <div class="meta"><b class="price" style="font-size:1rem">₱<?php echo e(number_format(($x->price+$x->price_adjustment)*(1-$x->discount_percent/100),2)); ?></b><br><small>each</small></div>
   </div>
   <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
   <div class="empty">Your cart is empty. <a href="/shop">Browse products &rarr;</a></div>
   <?php endif; ?>
  </div>
 </div>

 <div>
  <div class="form-card">
   <h3>Delivery address</h3>
   <form method="post" action="/addresses"><?php echo csrf_field(); ?>
    <?php $__currentLoopData = ['recipient'=>'Recipient','phone'=>'Phone','street'=>'Street / house number','barangay'=>'Barangay','city'=>'City or municipality','province'=>'Province','postal_code'=>'Postal code']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $name=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <label><?php echo e($label); ?><input name="<?php echo e($name); ?>" <?php if($name!=='postal_code'): ?> required <?php endif; ?>></label>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <button class="btn" style="width:100%">Save address</button>
   </form>
  </div>

  <div class="form-card">
   <h3>Checkout</h3>
   <form method="post" action="/checkout"><?php echo csrf_field(); ?>
    <label>Delivery address<select name="address_id" required>
     <?php $__empty_1 = true; $__currentLoopData = $addresses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><option value="<?php echo e($a->id); ?>"><?php echo e($a->street); ?>, <?php echo e($a->barangay); ?>, <?php echo e($a->city); ?>, <?php echo e($a->province); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><option value="">Add an address first</option><?php endif; ?>
    </select></label>
    <label>Payment method<select name="payment_method">
     <option value="cod">Cash on delivery</option>
     <option value="manual">Manual payment (awaiting confirmation)</option>
    </select></label>
    <label>Voucher code<input name="voucher" placeholder="Optional"></label>
    <button class="btn lime" style="width:100%">Place order</button>
   </form>
  </div>
 </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\Documents\LokaShop-all-in-one-v1\lokashop-ecommerce\resources\views/cart.blade.php ENDPATH**/ ?>
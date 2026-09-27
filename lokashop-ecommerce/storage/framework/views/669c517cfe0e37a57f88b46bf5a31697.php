<!doctype html>
<title>Shipping label</title>
<style>
body{font:18px system-ui;padding:25px;max-width:480px;border:2px solid #164e30}
img.logo{width:90px}
.qr-wrap{float:right;text-align:center}
.qr-wrap p{font:11px system-ui;color:#555;margin:4px 0 0}
</style>
<div class="qr-wrap">
  <div id="qr"></div>
  <p>Scan for order details</p>
</div>
<img class="logo" src="/logo.jpg">
<h1>LokaShop</h1>
<h2><?php echo e($parcel->tracking_code); ?></h2>
<p>Order #<?php echo e($order->id); ?></p>
<p>To: <?php echo e($address->recipient); ?> · <?php echo e($address->phone); ?></p>
<p><?php echo e($address->street); ?>, <?php echo e($address->barangay); ?>, <?php echo e($address->city); ?>, <?php echo e($address->province); ?> <?php echo e($address->postal_code); ?></p>
<button onclick="print()">Print</button>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
new QRCode(document.getElementById('qr'), {
  text: <?php echo json_encode($trackUrl, 15, 512) ?>,
  width: 110,
  height: 110,
  correctLevel: QRCode.CorrectLevel.M
});
</script>
<?php /**PATH C:\Users\Administrator\Documents\LokaShop-all-in-one-v1\lokashop-ecommerce\resources\views/label.blade.php ENDPATH**/ ?>
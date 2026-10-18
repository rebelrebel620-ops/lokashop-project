<!doctype html>
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Shipping label</title>
<style>
body{font:18px system-ui;padding:25px;max-width:480px;border:2px solid #164e30}
img.logo{width:90px}
.qr-wrap{float:right;text-align:center}
#qr{display:inline-block;padding:12px;background:#fff}
.qr-wrap p{font:11px system-ui;color:#555;margin:4px 0 0}
</style>
<div class="qr-wrap">
  <div id="qr"></div>
  <p>Scan for order details</p>
</div>
<img class="logo" src="/logo.jpg">
<h1>LokaShop</h1>
<h2>{{$parcel->tracking_code}}</h2>
<p>Order #{{$order->id}}</p>
<p>To: {{$address->recipient}} · {{$address->phone}}</p>
<p>{{$address->street}}, {{$address->barangay}}, {{$address->city}}, {{$address->province}} {{$address->postal_code}}</p>
<button onclick="print()">Print</button>

<script src="{{ asset('js/vendor/qrcode.min.js') }}"></script>
<script>
new QRCode(document.getElementById('qr'), {
  text: @json($trackUrl),
  width: 160,
  height: 160,
  correctLevel: QRCode.CorrectLevel.M
});
</script>

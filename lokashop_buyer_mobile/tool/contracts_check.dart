import 'package:lokashop_buyer/services/contracts.dart';
import 'package:lokashop_buyer/config.dart';

void check(bool value, String message) {
  if (!value) throw StateError(message);
}

void main() {
  final base = Uri.parse(apiBaseUrl);
  final rewritten = Uri.parse(
    mediaUrl('http://127.0.0.1:8000/api/orders/7/proof'),
  );
  check(
    rewritten.origin == base.origin,
    'Private images must use configured API origin without retaining old localhost port',
  );
  check(
    rewritten.path == '/api/orders/7/proof',
    'Private proof path is preserved',
  );
  check(
    mediaUrl('uploads/example.jpg').contains('/media/uploads/example.jpg'),
    'Uploaded product images use media endpoint',
  );
  check(
    mediaUrl('javascript:alert(1)').isEmpty,
    'Non-image URL schemes are rejected',
  );
  check(
    mediaUrl('https://images.example.test/photo.png') ==
        'https://images.example.test/photo.png',
    'External public photo URLs are retained',
  );
  check(
    cartUnit({
          'price': '100',
          'price_adjustment': '20',
          'discount_percent': '10',
        }) ==
        108,
    'Variant adjustments and discounts are included',
  );
  check(
    cartUnit({'price': 100, 'effective_price': 65}) == 65,
    'Server promotional unit price takes precedence',
  );
  final keys = List.generate(100, (_) => requestKey());
  check(
    keys.toSet().length == 100 &&
        keys.every((k) => RegExp(r'^[a-f0-9]{48}$').hasMatch(k)),
    'Checkout request keys are unique and server compatible',
  );
  check(
    Uri.parse(displayPhotoUrl('uploads/example.jpg')).queryParameters['size'] ==
        '400',
    'Product cards request compressed 400px copies',
  );
  check(
    Uri.parse(
          displayPhotoUrl('variants/example.png', size: 1200),
        ).queryParameters['size'] ==
        '1200',
    'Product details request compressed 1200px copies',
  );
  check(
    displayPhotoUrl('/api/orders/7/proof?original=1') ==
        mediaUrl('/api/orders/7/proof?original=1'),
    'Private original proof URLs keep their access route and query',
  );
  check(
    displayPhotoUrl('https://images.example.test/photo.png') ==
        'https://images.example.test/photo.png',
    'External images are not sent unsupported resize parameters',
  );
  check(
    displayPhotoUrl('').isEmpty,
    'Empty image paths cannot cause an origin parsing exception',
  );
  check(
    Uri.parse(
          displayPhotoUrl('/media/uploads/example.jpg?v=2'),
        ).queryParameters['v'] ==
        '2',
    'Image query parameters survive resize selection',
  );
  // ignore: avoid_print
  print('PASS: 14 native contract checks.');
}

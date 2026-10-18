// ignore_for_file: avoid_print
import 'package:lokashop_rider/models.dart';

void main() {
  int count = 0;
  void check(bool ok, String text) {
    if (!ok) throw StateError(text);
    count++;
  }

  final rider = Rider.fromJson({
    'id': '3',
    'name': 'Test Rider',
    'email': 'r@example.test',
    'online': true,
    'courier_type': 'driver',
    'license_number': 'A01-23-456789',
  });
  check(
    rider.online && rider.courierType == 'driver',
    'Driver and online state',
  );
  final cached = Rider.fromJson(rider.toJson());
  check(
    cached.online && cached.extra['license_number'] == 'A01-23-456789',
    'Cached account fields preserved',
  );
  final delivery = Assignment.fromJson({
    'id': '4',
    'parcel_id': '5',
    'kind': 'delivery',
    'status': 'accepted',
    'payment': {'method': 'cod', 'amount': '398.50'},
    'actions': [
      {
        'type': 'status',
        'status': 'DELIVERED',
        'label': 'Deliver',
        'needs_proof': true,
      },
    ],
    'recipient': {'name': 'Buyer', 'phone': '09170000000'},
  });
  check(delivery.id == 4 && delivery.parcelId == 5, 'String MySQL IDs');
  check(delivery.payment?.amount == 398.50, 'COD decimal amount');
  check(
    delivery.actions.single.status == 'DELIVERED',
    'Server delivery action',
  );
  final transfer = Assignment.fromJson({
    'kind': 'transfer',
    'centers': [
      {'name': 'Source', 'stage': 'Origin'},
      {'name': 'Destination', 'stage': 'Destination'},
    ],
    'actions': [
      {'type': 'transfer_depart', 'label': 'Depart'},
    ],
  });
  check(
    transfer.centers.length == 2 &&
        transfer.actions.single.type == 'transfer_depart',
    'Transfer routing and action',
  );
  final pickup = Assignment.fromJson({
    'kind': 'pickup',
    'seller': {
      'name': 'Store',
      'pickup_address': {'street': 'Example street'},
    },
  });
  check(
    pickup.isPickup &&
        pickup.seller?.pickupAddress?['street'] == 'Example street',
    'Seller pickup location',
  );
  final empty = Assignment.fromJson({'status': 'expired', 'actions': []});
  check(
    empty.actions.isEmpty && empty.recipient == null,
    'Expired offer has no actions or recipient',
  );
  check(peso(50) == '₱50.00', 'Peso amount');
  check(prettyStatus('OUT_FOR_DELIVERY') == 'Out For Delivery', 'Status label');
  print('PASS: $count courier model contract checks.');
}

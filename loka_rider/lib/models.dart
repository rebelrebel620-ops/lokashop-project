// Plain data classes for the rider API. MySQL decimals can arrive as strings,
// so every number goes through the tolerant helpers below.

int _i(dynamic v) => v is int ? v : (v is num ? v.toInt() : int.tryParse('$v') ?? 0);
double _d(dynamic v) => v is num ? v.toDouble() : double.tryParse('$v') ?? 0;
String? _s(dynamic v) => v == null ? null : '$v';

/// Peso formatting without needing the intl package.
String peso(double v) => '\u20B1${v.toStringAsFixed(2)}';

/// "2026-10-04 15:20:11" -> "2026-10-04 15:20"
String shortDate(String? v) => v == null ? '' : (v.length >= 16 ? v.substring(0, 16) : v);

String prettyStatus(String s) => s
    .split('_')
    .map((w) => w.isEmpty ? w : w[0].toUpperCase() + w.substring(1).toLowerCase())
    .join(' ');

class Rider {
  final int id;
  final String name;
  final String email;
  final String? phone;
  final String? vehicle;

  const Rider({required this.id, required this.name, required this.email, this.phone, this.vehicle});

  factory Rider.fromJson(Map<String, dynamic> j) => Rider(
        id: _i(j['id']),
        name: _s(j['name']) ?? '',
        email: _s(j['email']) ?? '',
        phone: _s(j['phone']),
        vehicle: _s(j['vehicle']),
      );

  Map<String, dynamic> toJson() => {'id': id, 'name': name, 'email': email, 'phone': phone, 'vehicle': vehicle};
}

class RiderStats {
  final int pickupsAssigned;
  final int deliveriesInProgress;
  final int completed;
  final double totalEarnings;

  const RiderStats({this.pickupsAssigned = 0, this.deliveriesInProgress = 0, this.completed = 0, this.totalEarnings = 0});

  factory RiderStats.fromJson(Map<String, dynamic> j) => RiderStats(
        pickupsAssigned: _i(j['pickups_assigned']),
        deliveriesInProgress: _i(j['deliveries_in_progress']),
        completed: _i(j['completed']),
        totalEarnings: _d(j['total_earnings']),
      );
}

/// A button the server says the rider may press right now.
class ActionItem {
  final String type; // 'accept' | 'status'
  final String? status; // PICKED_UP, OUT_FOR_DELIVERY, DELIVERED, DELIVERY_FAILED
  final String label;
  final bool needsReason;

  const ActionItem({required this.type, this.status, required this.label, this.needsReason = false});

  factory ActionItem.fromJson(Map<String, dynamic> j) => ActionItem(
        type: _s(j['type']) ?? '',
        status: _s(j['status']),
        label: _s(j['label']) ?? '',
        needsReason: j['needs_reason'] == true,
      );

  bool get isDestructive => status == 'DELIVERY_FAILED';
}

class Recipient {
  final String name;
  final String phone;
  final String street;
  final String barangay;
  final String? postalCode;

  const Recipient({required this.name, required this.phone, required this.street, required this.barangay, this.postalCode});

  factory Recipient.fromJson(Map<String, dynamic> j) => Recipient(
        name: _s(j['name']) ?? '',
        phone: _s(j['phone']) ?? '',
        street: _s(j['street']) ?? '',
        barangay: _s(j['barangay']) ?? '',
        postalCode: _s(j['postal_code']),
      );
}

class Seller {
  final String name;
  final String phone;
  const Seller({required this.name, required this.phone});
  factory Seller.fromJson(Map<String, dynamic> j) => Seller(name: _s(j['name']) ?? '', phone: _s(j['phone']) ?? '');
}

class Payment {
  final String method;
  final String status;
  final double amount;
  const Payment({required this.method, required this.status, required this.amount});
  factory Payment.fromJson(Map<String, dynamic> j) =>
      Payment(method: _s(j['method']) ?? '', status: _s(j['status']) ?? '', amount: _d(j['amount']));
}

class OrderLine {
  final String name;
  final int quantity;
  const OrderLine({required this.name, required this.quantity});
  factory OrderLine.fromJson(Map<String, dynamic> j) => OrderLine(name: _s(j['name']) ?? '', quantity: _i(j['quantity']));
}

class Assignment {
  final int id;
  final int parcelId;
  final int orderId;
  final String kind; // 'pickup' | 'delivery'
  final String status; // offered | accepted | completed | failed
  final double earnings;
  final String? createdAt;
  final String trackingCode;
  final String parcelStatus;
  final String? failureReason;
  final String city;
  final String province;
  final List<ActionItem> actions;
  final Recipient? recipient;
  final Seller? seller;
  final Payment? payment;
  final List<OrderLine> items;

  const Assignment({
    required this.id,
    required this.parcelId,
    required this.orderId,
    required this.kind,
    required this.status,
    required this.earnings,
    this.createdAt,
    required this.trackingCode,
    required this.parcelStatus,
    this.failureReason,
    required this.city,
    required this.province,
    this.actions = const [],
    this.recipient,
    this.seller,
    this.payment,
    this.items = const [],
  });

  bool get isPickup => kind == 'pickup';

  factory Assignment.fromJson(Map<String, dynamic> j) {
    List<T> list<T>(dynamic v, T Function(Map<String, dynamic>) f) =>
        v is List ? v.whereType<Map<String, dynamic>>().map(f).toList() : <T>[];
    Map<String, dynamic>? map(dynamic v) => v is Map<String, dynamic> ? v : null;

    final rec = map(j['recipient']);
    final sel = map(j['seller']);
    final pay = map(j['payment']);

    return Assignment(
      id: _i(j['id']),
      parcelId: _i(j['parcel_id']),
      orderId: _i(j['order_id']),
      kind: _s(j['kind']) ?? 'delivery',
      status: _s(j['status']) ?? '',
      earnings: _d(j['earnings']),
      createdAt: _s(j['created_at']),
      trackingCode: _s(j['tracking_code']) ?? '',
      parcelStatus: _s(j['parcel_status']) ?? '',
      failureReason: _s(j['failure_reason']),
      city: _s(j['city']) ?? '',
      province: _s(j['province']) ?? '',
      actions: list(j['actions'], ActionItem.fromJson),
      recipient: rec == null ? null : Recipient.fromJson(rec),
      seller: sel == null ? null : Seller.fromJson(sel),
      payment: pay == null ? null : Payment.fromJson(pay),
      items: list(j['items'], OrderLine.fromJson),
    );
  }
}

class EarningEntry {
  final int id;
  final int parcelId;
  final String kind;
  final double earnings;
  final String? completedAt;
  final String trackingCode;

  const EarningEntry({
    required this.id,
    required this.parcelId,
    required this.kind,
    required this.earnings,
    this.completedAt,
    required this.trackingCode,
  });

  factory EarningEntry.fromJson(Map<String, dynamic> j) => EarningEntry(
        id: _i(j['id']),
        parcelId: _i(j['parcel_id']),
        kind: _s(j['kind']) ?? '',
        earnings: _d(j['earnings']),
        completedAt: _s(j['completed_at']),
        trackingCode: _s(j['tracking_code']) ?? '',
      );
}

class EarningsSummary {
  final double total;
  final int count;
  final List<EarningEntry> entries;
  const EarningsSummary({required this.total, required this.count, required this.entries});

  factory EarningsSummary.fromJson(Map<String, dynamic> j) {
    final data = j['data'];
    return EarningsSummary(
      total: _d(j['total']),
      count: _i(j['count']),
      entries: data is List ? data.whereType<Map<String, dynamic>>().map(EarningEntry.fromJson).toList() : [],
    );
  }
}

/// Result of accept / status calls: a message plus the refreshed assignment.
class ActionResult {
  final String message;
  final Assignment? assignment;
  const ActionResult(this.message, this.assignment);
}

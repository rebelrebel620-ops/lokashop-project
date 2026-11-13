import 'dart:convert';
import 'package:flutter_test/flutter_test.dart';
import 'package:lokashop_rider/models.dart';

void main() {
  test('sorting center me response accepts the backend empty stats array', () {
    final response = jsonDecode('{"rider":{"id":1,"role":"sorting_center"},"stats":[]}') as Map;
    final rider = Rider.fromJson(Map<String, dynamic>.from(response['rider']));
    final stats = RiderStats.fromApiStats(response['stats']);
    expect(rider.isCenter, isTrue);
    expect(stats.pickupsAssigned, 0);
    expect(stats.deliveriesInProgress, 0);
    expect(stats.completed, 0);
    expect(stats.totalEarnings, 0);
  });

  test('rider/driver stats objects preserve counts and decimal earnings', () {
    final response = jsonDecode('{"stats":{"pickups_assigned":"3","deliveries_in_progress":2,"completed":7,"total_earnings":"125.50"}}') as Map;
    final stats = RiderStats.fromApiStats(response['stats']);
    expect(stats.pickupsAssigned, 3);
    expect(stats.deliveriesInProgress, 2);
    expect(stats.completed, 7);
    expect(stats.totalEarnings, 125.50);
  });

  test('missing or null stats use safe zero defaults', () {
    final missing = jsonDecode('{}') as Map;
    expect(RiderStats.fromApiStats(missing['stats']).completed, 0);
    expect(RiderStats.fromApiStats(null).totalEarnings, 0);
    expect(RiderStats.fromApiStats(<String, dynamic>{}).pickupsAssigned, 0);
  });
}

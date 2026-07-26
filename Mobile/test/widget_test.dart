import 'package:flutter_test/flutter_test.dart';
import 'package:ir4_mobile/features/scan/equipment_qr.dart';

void main() {
  test('widget test placeholder — QR parser smoke', () {
    expect(
      parseEquipmentQrToken('a1b2c3d4-e5f6-4789-a012-3456789abcde'),
      'a1b2c3d4-e5f6-4789-a012-3456789abcde',
    );
  });
}

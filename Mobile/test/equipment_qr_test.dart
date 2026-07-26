import 'package:flutter_test/flutter_test.dart';
import 'package:ir4_mobile/features/scan/equipment_qr.dart';

void main() {
  group('parseEquipmentQrToken', () {
    test('returns lowercase bare UUID', () {
      const String inputUuid =
          'A1B2C3D4-E5F6-4789-A012-3456789ABCDE';
      const String expectedUuid =
          'a1b2c3d4-e5f6-4789-a012-3456789abcde';

      final String? actualUuid = parseEquipmentQrToken(inputUuid);

      expect(actualUuid, expectedUuid);
    });

    test('extracts token from absolute /e/{token} URL', () {
      const String inputUrl =
          'https://ir4.local/e/a1b2c3d4-e5f6-4789-a012-3456789abcde';
      const String expectedUuid =
          'a1b2c3d4-e5f6-4789-a012-3456789abcde';

      final String? actualUuid = parseEquipmentQrToken(inputUrl);

      expect(actualUuid, expectedUuid);
    });

    test('extracts token from path-like string with /e/', () {
      const String inputPath =
          'http://10.0.0.5/e/a1b2c3d4-e5f6-4789-a012-3456789abcde/extra';
      const String expectedUuid =
          'a1b2c3d4-e5f6-4789-a012-3456789abcde';

      final String? actualUuid = parseEquipmentQrToken(inputPath);

      expect(actualUuid, expectedUuid);
    });

    test('returns null for empty and invalid values', () {
      expect(parseEquipmentQrToken(''), isNull);
      expect(parseEquipmentQrToken('   '), isNull);
      expect(parseEquipmentQrToken('not-a-token'), isNull);
      expect(parseEquipmentQrToken('https://ir4.local/equipment/1'), isNull);
    });
  });
}

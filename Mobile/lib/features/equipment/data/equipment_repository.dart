import 'package:ir4_mobile/core/network/api_client.dart';
import 'package:ir4_mobile/features/equipment/domain/equipment_models.dart';

abstract interface class EquipmentRepository {
  Future<EquipmentScanResult> scanByToken(String qrToken);

  Future<EquipmentScanResult> checkout({
    required String equipmentUuid,
    required CheckoutRequest request,
  });

  Future<EquipmentScanResult> returnItem({
    required String equipmentUuid,
    required ReturnRequest request,
  });
}

final class EquipmentRepositoryImpl implements EquipmentRepository {
  EquipmentRepositoryImpl({required ApiClient apiClient}) : _apiClient = apiClient;

  final ApiClient _apiClient;

  @override
  Future<EquipmentScanResult> scanByToken(String qrToken) async {
    final Map<String, dynamic> data = await _apiClient.getJson(
      '/api/mobile/equipment/by-token/$qrToken',
    );
    return EquipmentScanResult.fromJson(data);
  }

  @override
  Future<EquipmentScanResult> checkout({
    required String equipmentUuid,
    required CheckoutRequest request,
  }) async {
    final Map<String, dynamic> data = await _apiClient.postJson(
      '/api/mobile/equipment/$equipmentUuid/checkout',
      body: request.toJson(),
    );
    return EquipmentScanResult(
      equipment: EquipmentSummary.fromJson(
        data['equipment'] as Map<String, dynamic>,
      ),
      workers: const <NamedRef>[],
      zones: const <NamedRef>[],
      canCheckout: true,
    );
  }

  @override
  Future<EquipmentScanResult> returnItem({
    required String equipmentUuid,
    required ReturnRequest request,
  }) async {
    final Map<String, dynamic> data = await _apiClient.postJson(
      '/api/mobile/equipment/$equipmentUuid/return',
      body: request.toJson(),
    );
    return EquipmentScanResult(
      equipment: EquipmentSummary.fromJson(
        data['equipment'] as Map<String, dynamic>,
      ),
      workers: const <NamedRef>[],
      zones: const <NamedRef>[],
      canCheckout: true,
    );
  }
}

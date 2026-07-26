import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ir4_mobile/core/di/injector.dart';
import 'package:ir4_mobile/core/network/api_exception.dart';
import 'package:ir4_mobile/features/equipment/data/equipment_repository.dart';
import 'package:ir4_mobile/features/equipment/domain/equipment_models.dart';
import 'package:ir4_mobile/features/equipment/presentation/equipment_controller.dart';
import 'package:ir4_mobile/features/equipment/presentation/equipment_state.dart';

final class FakeEquipmentRepository implements EquipmentRepository {
  FakeEquipmentRepository({
    required this.scanResult,
    this.checkoutResult,
    this.returnResult,
    this.scanError,
    this.checkoutError,
  });

  EquipmentScanResult scanResult;
  EquipmentScanResult? checkoutResult;
  EquipmentScanResult? returnResult;
  ApiException? scanError;
  ApiException? checkoutError;
  CheckoutRequest? lastCheckout;
  ReturnRequest? lastReturn;

  @override
  Future<EquipmentScanResult> scanByToken(String qrToken) async {
    if (scanError != null) {
      throw scanError!;
    }
    return scanResult;
  }

  @override
  Future<EquipmentScanResult> checkout({
    required String equipmentUuid,
    required CheckoutRequest request,
  }) async {
    lastCheckout = request;
    if (checkoutError != null) {
      throw checkoutError!;
    }
    return checkoutResult ?? scanResult;
  }

  @override
  Future<EquipmentScanResult> returnItem({
    required String equipmentUuid,
    required ReturnRequest request,
  }) async {
    lastReturn = request;
    return returnResult ?? scanResult;
  }
}

EquipmentSummary buildEquipment({
  required String checkoutState,
  bool isCheckoutable = true,
}) {
  return EquipmentSummary(
    id: 7,
    uuid: 'equip-uuid',
    equipmentCode: 'EQ-007',
    qrToken: 'a1b2c3d4-e5f6-4789-a012-3456789abcde',
    name: 'Harness',
    equipmentType: 'PPE',
    status: 'in_service',
    statusLabel: 'In service',
    isCheckoutable: isCheckoutable,
    checkoutState: checkoutState,
  );
}

void main() {
  late FakeEquipmentRepository fakeRepository;
  late ProviderContainer container;

  final EquipmentScanResult inputScan = EquipmentScanResult(
    equipment: buildEquipment(checkoutState: 'available'),
    workers: const <NamedRef>[
      NamedRef(id: 3, uuid: 'worker-uuid', name: 'Ali'),
    ],
    zones: const <NamedRef>[
      NamedRef(id: 2, uuid: 'zone-uuid', name: 'Zone A'),
    ],
    canCheckout: true,
  );

  setUp(() async {
    await resetDependencies();
    fakeRepository = FakeEquipmentRepository(scanResult: inputScan);
    getIt.registerSingleton<EquipmentRepository>(fakeRepository);
    container = ProviderContainer();
  });

  tearDown(() async {
    container.dispose();
    await resetDependencies();
  });

  test('loadByToken stores scan payload when lookup succeeds', () async {
    final EquipmentController controller =
        container.read(equipmentControllerProvider.notifier);

    await controller.loadByToken('a1b2c3d4-e5f6-4789-a012-3456789abcde');

    final EquipmentState actualState =
        container.read(equipmentControllerProvider);
    expect(actualState, isA<EquipmentLoaded>());
    expect(
      (actualState as EquipmentLoaded).result.equipment.equipmentCode,
      'EQ-007',
    );
  });

  test('loadByToken stores error when repository throws', () async {
    fakeRepository.scanError = const ApiException(
      code: 'FORBIDDEN',
      message: 'Forbidden',
    );
    final EquipmentController controller =
        container.read(equipmentControllerProvider.notifier);

    await controller.loadByToken('a1b2c3d4-e5f6-4789-a012-3456789abcde');

    final EquipmentState actualState =
        container.read(equipmentControllerProvider);
    expect(actualState, isA<EquipmentError>());
    expect((actualState as EquipmentError).message, 'Forbidden');
  });

  test('checkout updates loaded equipment on success', () async {
    fakeRepository.checkoutResult = EquipmentScanResult(
      equipment: buildEquipment(checkoutState: 'checked_out'),
      workers: inputScan.workers,
      zones: inputScan.zones,
      canCheckout: true,
    );
    final EquipmentController controller =
        container.read(equipmentControllerProvider.notifier);
    await controller.loadByToken('a1b2c3d4-e5f6-4789-a012-3456789abcde');

    final bool actualOk = await controller.checkout(
      const CheckoutRequest(workerId: 3, reason: 'Task'),
    );

    expect(actualOk, isTrue);
    expect(fakeRepository.lastCheckout?.workerId, 3);
    final EquipmentState actualState =
        container.read(equipmentControllerProvider);
    expect(
      (actualState as EquipmentLoaded).result.equipment.checkoutState,
      'checked_out',
    );
  });

  test('checkout returns false and sets error on conflict', () async {
    fakeRepository.checkoutError = const ApiException(
      code: 'CONFLICT',
      message: 'Already checked out',
    );
    final EquipmentController controller =
        container.read(equipmentControllerProvider.notifier);
    await controller.loadByToken('a1b2c3d4-e5f6-4789-a012-3456789abcde');

    final bool actualOk = await controller.checkout(
      const CheckoutRequest(workerId: 3),
    );

    expect(actualOk, isFalse);
    expect(container.read(equipmentControllerProvider), isA<EquipmentError>());
  });
}

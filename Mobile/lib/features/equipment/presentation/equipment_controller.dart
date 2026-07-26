import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ir4_mobile/core/di/injector.dart';
import 'package:ir4_mobile/core/network/api_exception.dart';
import 'package:ir4_mobile/features/equipment/data/equipment_repository.dart';
import 'package:ir4_mobile/features/equipment/domain/equipment_models.dart';
import 'package:ir4_mobile/features/equipment/presentation/equipment_state.dart';

final equipmentControllerProvider =
    NotifierProvider<EquipmentController, EquipmentState>(
  EquipmentController.new,
);

final class EquipmentController extends Notifier<EquipmentState> {
  EquipmentRepository get _repository => getIt<EquipmentRepository>();

  @override
  EquipmentState build() => const EquipmentState.idle();

  Future<void> loadByToken(String qrToken) async {
    state = const EquipmentState.loading();
    try {
      final EquipmentScanResult result = await _repository.scanByToken(qrToken);
      state = EquipmentState.loaded(result);
    } on ApiException catch (error) {
      state = EquipmentState.error(error.message);
    } catch (_) {
      state = const EquipmentState.error('Could not load equipment.');
    }
  }

  Future<bool> checkout(CheckoutRequest request) async {
    final EquipmentState current = state;
    if (current is! EquipmentLoaded) {
      return false;
    }
    try {
      final EquipmentScanResult updated = await _repository.checkout(
        equipmentUuid: current.result.equipment.uuid,
        request: request,
      );
      final EquipmentScanResult merged = EquipmentScanResult(
        equipment: updated.equipment,
        workers: current.result.workers,
        zones: current.result.zones,
        canCheckout: current.result.canCheckout,
      );
      state = EquipmentState.loaded(merged);
      return true;
    } on ApiException catch (error) {
      state = EquipmentState.error(error.message);
      return false;
    } catch (_) {
      state = const EquipmentState.error('Checkout failed.');
      return false;
    }
  }

  Future<bool> returnItem(ReturnRequest request) async {
    final EquipmentState current = state;
    if (current is! EquipmentLoaded) {
      return false;
    }
    try {
      final EquipmentScanResult updated = await _repository.returnItem(
        equipmentUuid: current.result.equipment.uuid,
        request: request,
      );
      final EquipmentScanResult merged = EquipmentScanResult(
        equipment: updated.equipment,
        workers: current.result.workers,
        zones: current.result.zones,
        canCheckout: current.result.canCheckout,
      );
      state = EquipmentState.loaded(merged);
      return true;
    } on ApiException catch (error) {
      state = EquipmentState.error(error.message);
      return false;
    } catch (_) {
      state = const EquipmentState.error('Return failed.');
      return false;
    }
  }

  void reset() {
    state = const EquipmentState.idle();
  }
}

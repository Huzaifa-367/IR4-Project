import 'package:freezed_annotation/freezed_annotation.dart';
import 'package:ir4_mobile/features/equipment/domain/equipment_models.dart';

part 'equipment_state.freezed.dart';

@freezed
sealed class EquipmentState with _$EquipmentState {
  const factory EquipmentState.idle() = EquipmentIdle;
  const factory EquipmentState.loading() = EquipmentLoading;
  const factory EquipmentState.loaded(EquipmentScanResult result) =
      EquipmentLoaded;
  const factory EquipmentState.error(String message) = EquipmentError;
}

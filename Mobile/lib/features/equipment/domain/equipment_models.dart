final class NamedRef {
  const NamedRef({
    required this.id,
    required this.uuid,
    required this.name,
  });

  final int id;
  final String uuid;
  final String name;

  factory NamedRef.fromJson(Map<String, dynamic> json) {
    return NamedRef(
      id: json['id'] as int,
      uuid: json['uuid'] as String? ?? '',
      name: json['name'] as String? ?? '—',
    );
  }
}

final class OpenCheckout {
  const OpenCheckout({
    required this.id,
    required this.uuid,
    required this.workerId,
    required this.workerName,
    this.reason,
    this.zoneName,
    this.expectedReturnAt,
    this.checkedOutAt,
    this.checkedOutByName,
    this.conditionOut,
    this.notes,
    this.isOverdueReturn = false,
  });

  final int id;
  final String uuid;
  final int workerId;
  final String workerName;
  final String? reason;
  final String? zoneName;
  final String? expectedReturnAt;
  final String? checkedOutAt;
  final String? checkedOutByName;
  final String? conditionOut;
  final String? notes;
  final bool isOverdueReturn;

  factory OpenCheckout.fromJson(Map<String, dynamic> json) {
    final Map<String, dynamic>? worker = json['worker'] as Map<String, dynamic>?;
    final Map<String, dynamic>? zone = json['zone'] as Map<String, dynamic>?;
    final Map<String, dynamic>? checkedOutBy =
        json['checked_out_by_user'] as Map<String, dynamic>?;
    return OpenCheckout(
      id: json['id'] as int,
      uuid: json['uuid'] as String,
      workerId: json['worker_id'] as int,
      workerName: worker?['name'] as String? ?? 'Worker',
      reason: json['reason'] as String?,
      zoneName: zone?['name'] as String?,
      expectedReturnAt: json['expected_return_at'] as String?,
      checkedOutAt: json['checked_out_at'] as String?,
      checkedOutByName: checkedOutBy?['name'] as String?,
      conditionOut: json['condition_out'] as String?,
      notes: json['notes'] as String?,
      isOverdueReturn: json['is_overdue_return'] as bool? ?? false,
    );
  }
}

final class EquipmentSchedule {
  const EquipmentSchedule({
    required this.id,
    required this.scheduleType,
    required this.scheduleTypeLabel,
    required this.intervalDays,
    this.notes,
  });

  final int id;
  final String scheduleType;
  final String scheduleTypeLabel;
  final int intervalDays;
  final String? notes;

  factory EquipmentSchedule.fromJson(Map<String, dynamic> json) {
    return EquipmentSchedule(
      id: json['id'] as int,
      scheduleType: json['schedule_type'] as String? ?? '',
      scheduleTypeLabel:
          json['schedule_type_label'] as String? ?? json['schedule_type'] as String? ?? '',
      intervalDays: json['interval_days'] as int? ?? 0,
      notes: json['notes'] as String?,
    );
  }
}

final class EquipmentInspectionItem {
  const EquipmentInspectionItem({
    required this.id,
    required this.outcome,
    required this.outcomeLabel,
    this.inspectedAt,
    this.notes,
    this.inspectorName,
    this.nextDue,
  });

  final int id;
  final String outcome;
  final String outcomeLabel;
  final String? inspectedAt;
  final String? notes;
  final String? inspectorName;
  final String? nextDue;

  factory EquipmentInspectionItem.fromJson(Map<String, dynamic> json) {
    final Map<String, dynamic>? inspector =
        json['inspector'] as Map<String, dynamic>?;
    return EquipmentInspectionItem(
      id: json['id'] as int,
      outcome: json['outcome'] as String? ?? '',
      outcomeLabel: json['outcome_label'] as String? ?? json['outcome'] as String? ?? '',
      inspectedAt: json['inspected_at'] as String?,
      notes: json['notes'] as String?,
      inspectorName: inspector?['name'] as String?,
      nextDue: json['next_due'] as String?,
    );
  }
}

final class EquipmentMaintenanceItem {
  const EquipmentMaintenanceItem({
    required this.id,
    required this.maintenanceType,
    required this.maintenanceTypeLabel,
    this.performedAt,
    this.description,
    this.performedByName,
    this.nextDue,
  });

  final int id;
  final String maintenanceType;
  final String maintenanceTypeLabel;
  final String? performedAt;
  final String? description;
  final String? performedByName;
  final String? nextDue;

  factory EquipmentMaintenanceItem.fromJson(Map<String, dynamic> json) {
    return EquipmentMaintenanceItem(
      id: json['id'] as int,
      maintenanceType: json['maintenance_type'] as String? ?? '',
      maintenanceTypeLabel: json['maintenance_type_label'] as String? ??
          json['maintenance_type'] as String? ??
          '',
      performedAt: json['performed_at'] as String?,
      description: json['description'] as String?,
      performedByName: json['performed_by_name'] as String?,
      nextDue: json['next_due'] as String?,
    );
  }
}

final class EquipmentDocumentItem {
  const EquipmentDocumentItem({
    required this.id,
    required this.uuid,
    required this.title,
    this.mime,
    this.createdAt,
  });

  final int id;
  final String uuid;
  final String title;
  final String? mime;
  final String? createdAt;

  factory EquipmentDocumentItem.fromJson(Map<String, dynamic> json) {
    return EquipmentDocumentItem(
      id: json['id'] as int,
      uuid: json['uuid'] as String? ?? '',
      title: json['title'] as String? ?? 'Document',
      mime: json['mime'] as String?,
      createdAt: json['created_at'] as String?,
    );
  }
}

final class EquipmentSummary {
  const EquipmentSummary({
    required this.id,
    required this.uuid,
    required this.equipmentCode,
    required this.qrToken,
    required this.name,
    required this.equipmentType,
    required this.status,
    required this.statusLabel,
    required this.isCheckoutable,
    required this.checkoutState,
    this.locationLabel,
    this.description,
    this.publicUrl,
    this.nextInspectionDue,
    this.nextServiceDue,
    this.isInspectionOverdue = false,
    this.isServiceOverdue = false,
    this.isDueSoon = false,
    this.openCheckout,
    this.schedules = const <EquipmentSchedule>[],
    this.inspections = const <EquipmentInspectionItem>[],
    this.maintenances = const <EquipmentMaintenanceItem>[],
    this.documents = const <EquipmentDocumentItem>[],
    this.createdAt,
    this.updatedAt,
  });

  final int id;
  final String uuid;
  final String equipmentCode;
  final String qrToken;
  final String name;
  final String equipmentType;
  final String status;
  final String statusLabel;
  final bool isCheckoutable;
  final String checkoutState;
  final String? locationLabel;
  final String? description;
  final String? publicUrl;
  final String? nextInspectionDue;
  final String? nextServiceDue;
  final bool isInspectionOverdue;
  final bool isServiceOverdue;
  final bool isDueSoon;
  final OpenCheckout? openCheckout;
  final List<EquipmentSchedule> schedules;
  final List<EquipmentInspectionItem> inspections;
  final List<EquipmentMaintenanceItem> maintenances;
  final List<EquipmentDocumentItem> documents;
  final String? createdAt;
  final String? updatedAt;

  bool get isCheckedOut =>
      checkoutState == 'checked_out' || checkoutState == 'overdue_return';

  bool get hasComplianceAlert =>
      isInspectionOverdue || isServiceOverdue || isDueSoon;

  factory EquipmentSummary.fromJson(Map<String, dynamic> json) {
    final Map<String, dynamic>? open =
        json['open_checkout'] as Map<String, dynamic>?;
    return EquipmentSummary(
      id: json['id'] as int,
      uuid: json['uuid'] as String,
      equipmentCode: json['equipment_code'] as String,
      qrToken: json['qr_token'] as String,
      name: json['name'] as String,
      equipmentType: json['equipment_type'] as String,
      status: json['status'] as String,
      statusLabel: json['status_label'] as String? ?? json['status'] as String,
      isCheckoutable: json['is_checkoutable'] as bool? ?? false,
      checkoutState: json['checkout_state'] as String,
      locationLabel: json['location_label'] as String?,
      description: json['description'] as String?,
      publicUrl: json['public_url'] as String?,
      nextInspectionDue: json['next_inspection_due'] as String?,
      nextServiceDue: json['next_service_due'] as String?,
      isInspectionOverdue: json['is_inspection_overdue'] as bool? ?? false,
      isServiceOverdue: json['is_service_overdue'] as bool? ?? false,
      isDueSoon: json['is_due_soon'] as bool? ?? false,
      openCheckout: open == null ? null : OpenCheckout.fromJson(open),
      schedules: (json['schedules'] as List<dynamic>? ?? <dynamic>[])
          .whereType<Map<String, dynamic>>()
          .map(EquipmentSchedule.fromJson)
          .toList(),
      inspections: (json['inspections'] as List<dynamic>? ?? <dynamic>[])
          .whereType<Map<String, dynamic>>()
          .map(EquipmentInspectionItem.fromJson)
          .toList(),
      maintenances: (json['maintenances'] as List<dynamic>? ?? <dynamic>[])
          .whereType<Map<String, dynamic>>()
          .map(EquipmentMaintenanceItem.fromJson)
          .toList(),
      documents: (json['documents'] as List<dynamic>? ?? <dynamic>[])
          .whereType<Map<String, dynamic>>()
          .map(EquipmentDocumentItem.fromJson)
          .toList(),
      createdAt: json['created_at'] as String?,
      updatedAt: json['updated_at'] as String?,
    );
  }
}

final class EquipmentScanResult {
  const EquipmentScanResult({
    required this.equipment,
    required this.workers,
    required this.zones,
    required this.canCheckout,
  });

  final EquipmentSummary equipment;
  final List<NamedRef> workers;
  final List<NamedRef> zones;
  final bool canCheckout;

  factory EquipmentScanResult.fromJson(Map<String, dynamic> json) {
    return EquipmentScanResult(
      equipment: EquipmentSummary.fromJson(
        json['equipment'] as Map<String, dynamic>,
      ),
      workers: (json['workers'] as List<dynamic>? ?? <dynamic>[])
          .whereType<Map<String, dynamic>>()
          .map(NamedRef.fromJson)
          .toList(),
      zones: (json['zones'] as List<dynamic>? ?? <dynamic>[])
          .whereType<Map<String, dynamic>>()
          .map(NamedRef.fromJson)
          .toList(),
      canCheckout: json['can_checkout'] as bool? ?? false,
    );
  }
}

final class CheckoutRequest {
  const CheckoutRequest({
    required this.workerId,
    this.reason,
    this.zoneId,
    this.expectedReturnAt,
    this.conditionOut,
    this.notes,
  });

  final int workerId;
  final String? reason;
  final int? zoneId;
  final String? expectedReturnAt;
  final String? conditionOut;
  final String? notes;

  Map<String, dynamic> toJson() {
    return <String, dynamic>{
      'worker_id': workerId,
      if (reason != null && reason!.isNotEmpty) 'reason': reason,
      if (zoneId != null) 'zone_id': zoneId,
      if (expectedReturnAt != null && expectedReturnAt!.isNotEmpty)
        'expected_return_at': expectedReturnAt,
      if (conditionOut != null && conditionOut!.isNotEmpty)
        'condition_out': conditionOut,
      if (notes != null && notes!.isNotEmpty) 'notes': notes,
    };
  }
}

final class ReturnRequest {
  const ReturnRequest({
    this.returnStatus,
    this.returnReason,
    this.conditionIn,
    this.notes,
  });

  final String? returnStatus;
  final String? returnReason;
  final String? conditionIn;
  final String? notes;

  Map<String, dynamic> toJson() {
    return <String, dynamic>{
      if (returnStatus != null && returnStatus!.isNotEmpty)
        'return_status': returnStatus,
      if (returnReason != null && returnReason!.isNotEmpty)
        'return_reason': returnReason,
      if (conditionIn != null && conditionIn!.isNotEmpty)
        'condition_in': conditionIn,
      if (notes != null && notes!.isNotEmpty) 'notes': notes,
    };
  }
}

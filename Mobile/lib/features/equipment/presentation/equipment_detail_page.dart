import 'dart:ui';

import 'package:auto_route/auto_route.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ir4_mobile/core/theme/app_theme.dart';
import 'package:ir4_mobile/core/utils/date_display.dart';
import 'package:ir4_mobile/core/widgets/app_background.dart';
import 'package:ir4_mobile/core/widgets/glass_card.dart';
import 'package:ir4_mobile/core/widgets/glossy_button.dart';
import 'package:ir4_mobile/core/widgets/status_pill.dart';
import 'package:ir4_mobile/features/equipment/domain/equipment_models.dart';
import 'package:ir4_mobile/features/equipment/presentation/checkout_sheet.dart';
import 'package:ir4_mobile/features/equipment/presentation/equipment_controller.dart';
import 'package:ir4_mobile/features/equipment/presentation/equipment_state.dart';
import 'package:ir4_mobile/features/equipment/presentation/return_sheet.dart';

@RoutePage()
class EquipmentDetailPage extends ConsumerStatefulWidget {
  const EquipmentDetailPage({
    super.key,
    @PathParam('qrToken') required this.qrToken,
  });

  final String qrToken;

  @override
  ConsumerState<EquipmentDetailPage> createState() =>
      _EquipmentDetailPageState();
}

class _EquipmentDetailPageState extends ConsumerState<EquipmentDetailPage> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref
          .read(equipmentControllerProvider.notifier)
          .loadByToken(widget.qrToken);
    });
  }

  Future<void> _onPrimary(EquipmentScanResult result) async {
    final bool wasCheckedOut = result.equipment.isCheckedOut;
    final bool ok;
    if (wasCheckedOut) {
      ok = await showReturnSheet(context: context, ref: ref);
    } else {
      ok = await showCheckoutSheet(context: context, ref: ref, scan: result);
    }
    if (!mounted) {
      return;
    }
    if (ok) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            wasCheckedOut ? 'Equipment returned.' : 'Equipment checked out.',
          ),
        ),
      );
      await ref
          .read(equipmentControllerProvider.notifier)
          .loadByToken(widget.qrToken);
    } else {
      final EquipmentState state = ref.read(equipmentControllerProvider);
      if (state is EquipmentError) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(state.message)));
        await ref
            .read(equipmentControllerProvider.notifier)
            .loadByToken(widget.qrToken);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final EquipmentState state = ref.watch(equipmentControllerProvider);
    final String? titleCode = state is EquipmentLoaded
        ? state.result.equipment.equipmentCode
        : null;

    return Scaffold(
      backgroundColor: Colors.transparent,
      extendBodyBehindAppBar: true,
      appBar: AppBar(
        title: Column(
          children: <Widget>[
            const Text('Equipment'),
            if (titleCode != null)
              Text(
                titleCode,
                style: const TextStyle(
                  color: AppTheme.textMuted,
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                ),
              ),
          ],
        ),
      ),
      body: AppBackground(
        child: SafeArea(
          child: switch (state) {
            EquipmentLoading() ||
            EquipmentIdle() => const Center(child: CircularProgressIndicator()),
            EquipmentError(:final String message) => _ErrorView(
              message: message,
              onRetry: () => ref
                  .read(equipmentControllerProvider.notifier)
                  .loadByToken(widget.qrToken),
            ),
            EquipmentLoaded(:final EquipmentScanResult result) => _LoadedBody(
              result: result,
              onPrimary: () => _onPrimary(result),
            ),
          },
        ),
      ),
    );
  }
}

class _LoadedBody extends StatelessWidget {
  const _LoadedBody({required this.result, required this.onPrimary});

  final EquipmentScanResult result;
  final VoidCallback onPrimary;

  @override
  Widget build(BuildContext context) {
    final EquipmentSummary equipment = result.equipment;
    final bool checkedOut = equipment.isCheckedOut;
    final bool showAction =
        result.canCheckout && (checkedOut || equipment.isCheckoutable);
    final List<EquipmentInspectionItem> recentInspections = equipment
        .inspections
        .take(5)
        .toList();
    final List<EquipmentMaintenanceItem> recentMaintenances = equipment
        .maintenances
        .take(5)
        .toList();

    return Column(
      children: <Widget>[
        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 20),
            children: <Widget>[
              _HeaderCard(equipment: equipment),
              if (equipment.hasComplianceAlert) ...<Widget>[
                const SizedBox(height: 12),
                _ComplianceBanner(equipment: equipment),
              ],
              if (equipment.openCheckout != null) ...<Widget>[
                const SizedBox(height: 12),
                _CustodyCard(checkout: equipment.openCheckout!),
              ],
              const SizedBox(height: 12),
              _SectionCard(
                title: 'Details',
                icon: Icons.info_outline_rounded,
                child: Column(
                  children: <Widget>[
                    _InfoRow(
                      icon: Icons.tag_rounded,
                      label: 'Code',
                      value: equipment.equipmentCode,
                    ),
                    _InfoRow(
                      icon: Icons.category_outlined,
                      label: 'Type',
                      value: equipment.equipmentType,
                    ),
                    _InfoRow(
                      icon: Icons.place_outlined,
                      label: 'Location',
                      value: equipment.locationLabel ?? '—',
                    ),
                    _InfoRow(
                      icon: Icons.swap_horiz_rounded,
                      label: 'Checkoutable',
                      value: equipment.isCheckoutable ? 'Yes' : 'No',
                    ),
                    _InfoRow(
                      icon: Icons.qr_code_2_rounded,
                      label: 'QR token',
                      value: equipment.qrToken,
                      copyable: true,
                      last:
                          equipment.description == null ||
                          equipment.description!.isEmpty,
                    ),
                    if (equipment.description != null &&
                        equipment.description!.isNotEmpty)
                      _InfoRow(
                        icon: Icons.notes_outlined,
                        label: 'Description',
                        value: equipment.description!,
                        last: true,
                      ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
              _SectionCard(
                title: 'Compliance',
                icon: Icons.fact_check_outlined,
                child: Column(
                  children: <Widget>[
                    _DueTile(
                      label: 'Next inspection',
                      value: DateDisplay.date(equipment.nextInspectionDue),
                      overdue: equipment.isInspectionOverdue,
                      dueSoon:
                          !equipment.isInspectionOverdue &&
                          equipment.isDueSoon &&
                          equipment.nextInspectionDue != null,
                    ),
                    const SizedBox(height: 10),
                    _DueTile(
                      label: 'Next service',
                      value: DateDisplay.date(equipment.nextServiceDue),
                      overdue: equipment.isServiceOverdue,
                      dueSoon:
                          !equipment.isServiceOverdue &&
                          equipment.isDueSoon &&
                          equipment.nextServiceDue != null,
                    ),
                  ],
                ),
              ),
              if (equipment.schedules.isNotEmpty) ...<Widget>[
                const SizedBox(height: 12),
                _SectionCard(
                  title: 'PM schedules',
                  icon: Icons.calendar_month_outlined,
                  child: Column(
                    children: equipment.schedules
                        .map(
                          (EquipmentSchedule schedule) => _ScheduleRow(
                            schedule: schedule,
                            last: schedule == equipment.schedules.last,
                          ),
                        )
                        .toList(),
                  ),
                ),
              ],
              if (recentInspections.isNotEmpty) ...<Widget>[
                const SizedBox(height: 12),
                _SectionCard(
                  title: 'Recent inspections',
                  icon: Icons.assignment_turned_in_outlined,
                  trailing: '${equipment.inspections.length} total',
                  child: Column(
                    children: recentInspections
                        .map(
                          (EquipmentInspectionItem item) => _HistoryRow(
                            title: item.outcomeLabel,
                            subtitle: <String>[
                              if (item.inspectedAt != null)
                                DateDisplay.date(item.inspectedAt),
                              if (item.inspectorName != null)
                                item.inspectorName!,
                            ].join(' · '),
                            note: item.notes,
                            accent: item.outcome == 'fail'
                                ? AppTheme.danger
                                : item.outcome == 'pass_with_notes'
                                ? AppTheme.warning
                                : AppTheme.success,
                            last: item == recentInspections.last,
                          ),
                        )
                        .toList(),
                  ),
                ),
              ],
              if (recentMaintenances.isNotEmpty) ...<Widget>[
                const SizedBox(height: 12),
                _SectionCard(
                  title: 'Recent maintenance',
                  icon: Icons.build_outlined,
                  trailing: '${equipment.maintenances.length} total',
                  child: Column(
                    children: recentMaintenances
                        .map(
                          (EquipmentMaintenanceItem item) => _HistoryRow(
                            title: item.maintenanceTypeLabel,
                            subtitle: <String>[
                              if (item.performedAt != null)
                                DateDisplay.date(item.performedAt),
                              if (item.performedByName != null)
                                item.performedByName!,
                            ].join(' · '),
                            note: item.description,
                            accent: item.maintenanceType == 'corrective'
                                ? AppTheme.warning
                                : AppTheme.accent,
                            last: item == recentMaintenances.last,
                          ),
                        )
                        .toList(),
                  ),
                ),
              ],
              if (equipment.documents.isNotEmpty) ...<Widget>[
                const SizedBox(height: 12),
                _SectionCard(
                  title: 'Documents',
                  icon: Icons.folder_outlined,
                  trailing: '${equipment.documents.length}',
                  child: Column(
                    children: equipment.documents
                        .map(
                          (EquipmentDocumentItem doc) => _DocumentRow(
                            document: doc,
                            last: doc == equipment.documents.last,
                          ),
                        )
                        .toList(),
                  ),
                ),
              ],
              if (!result.canCheckout) ...<Widget>[
                const SizedBox(height: 12),
                const _NoticeCard(
                  icon: Icons.visibility_outlined,
                  text:
                      'You can view this item but do not have permission to check it out or return it.',
                ),
              ] else if (!checkedOut && !equipment.isCheckoutable) ...<Widget>[
                const SizedBox(height: 12),
                const _NoticeCard(
                  icon: Icons.block_outlined,
                  text: 'This item is not available for checkout.',
                ),
              ],
              const SizedBox(height: 8),
              Text(
                'Updated ${DateDisplay.dateTime(equipment.updatedAt)}',
                textAlign: TextAlign.center,
                style: const TextStyle(color: Color(0xFF5B6779), fontSize: 12),
              ),
            ],
          ),
        ),
        if (showAction)
          _StickyActionBar(
            checkedOut: checkedOut,
            overdue: equipment.openCheckout?.isOverdueReturn ?? false,
            onPrimary: onPrimary,
          ),
      ],
    );
  }
}

class _StickyActionBar extends StatelessWidget {
  const _StickyActionBar({
    required this.checkedOut,
    required this.overdue,
    required this.onPrimary,
  });

  final bool checkedOut;
  final bool overdue;
  final VoidCallback onPrimary;

  @override
  Widget build(BuildContext context) {
    return ClipRect(
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: 20, sigmaY: 20),
        child: Container(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
          decoration: BoxDecoration(
            color: const Color(0xCC0A1120),
            border: Border(
              top: BorderSide(color: Colors.white.withValues(alpha: 0.1)),
            ),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text(
                checkedOut
                    ? (overdue
                          ? 'This item is overdue — confirm return'
                          : 'Scan complete — return this item')
                    : 'Scan complete — issue this item',
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: AppTheme.textMuted,
                  fontSize: 12.5,
                  fontWeight: FontWeight.w500,
                ),
              ),
              const SizedBox(height: 10),
              GlossyButton(
                label: checkedOut ? 'Return item' : 'Check out',
                icon: checkedOut
                    ? Icons.assignment_return_rounded
                    : Icons.logout_rounded,
                variant: checkedOut
                    ? GlossyVariant.warning
                    : GlossyVariant.primary,
                onPressed: onPrimary,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _HeaderCard extends StatelessWidget {
  const _HeaderCard({required this.equipment});

  final EquipmentSummary equipment;

  @override
  Widget build(BuildContext context) {
    final bool checkedOut = equipment.isCheckedOut;
    return GlassCard(
      padding: const EdgeInsets.all(22),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Container(
                width: 56,
                height: 56,
                decoration: BoxDecoration(
                  gradient: AppTheme.primaryGradient,
                  borderRadius: BorderRadius.circular(16),
                ),
                child: const Icon(
                  Icons.inventory_2_rounded,
                  color: Colors.white,
                ),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      equipment.name,
                      style: const TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.w700,
                        letterSpacing: -0.4,
                        color: AppTheme.text,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      equipment.equipmentCode,
                      style: const TextStyle(
                        color: AppTheme.textMuted,
                        fontFeatures: <FontFeature>[
                          FontFeature.tabularFigures(),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: <Widget>[
              StatusPill(label: equipment.statusLabel),
              StatusPill(
                label: equipment.checkoutState.replaceAll('_', ' '),
                color: checkedOut ? AppTheme.warning : AppTheme.success,
              ),
              StatusPill(
                label: equipment.equipmentType,
                color: AppTheme.accentAlt,
              ),
              if (equipment.isCheckoutable)
                const StatusPill(label: 'checkoutable', color: AppTheme.accent),
            ],
          ),
        ],
      ),
    );
  }
}

class _ComplianceBanner extends StatelessWidget {
  const _ComplianceBanner({required this.equipment});

  final EquipmentSummary equipment;

  @override
  Widget build(BuildContext context) {
    final bool overdue =
        equipment.isInspectionOverdue || equipment.isServiceOverdue;
    final Color color = overdue ? AppTheme.danger : AppTheme.warning;
    final String message;
    if (equipment.isInspectionOverdue && equipment.isServiceOverdue) {
      message = 'Inspection and service are overdue.';
    } else if (equipment.isInspectionOverdue) {
      message = 'Inspection is overdue.';
    } else if (equipment.isServiceOverdue) {
      message = 'Service is overdue.';
    } else {
      message = 'Inspection or service is due within 7 days.';
    }
    return GlassCard(
      padding: const EdgeInsets.all(14),
      child: Row(
        children: <Widget>[
          Icon(
            overdue ? Icons.warning_amber_rounded : Icons.schedule_rounded,
            color: color,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              message,
              style: TextStyle(
                color: color,
                fontWeight: FontWeight.w600,
                height: 1.35,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CustodyCard extends StatelessWidget {
  const _CustodyCard({required this.checkout});

  final OpenCheckout checkout;

  @override
  Widget build(BuildContext context) {
    final Color accent = checkout.isOverdueReturn
        ? AppTheme.danger
        : AppTheme.warning;
    return GlassCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Icon(Icons.person_pin_circle_outlined, color: accent, size: 20),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  checkout.isOverdueReturn
                      ? 'OVERDUE CUSTODY'
                      : 'CURRENTLY HELD',
                  style: TextStyle(
                    color: accent,
                    fontWeight: FontWeight.w700,
                    fontSize: 12.5,
                    letterSpacing: 0.5,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          _InfoRow(
            icon: Icons.badge_outlined,
            label: 'Holder',
            value: checkout.workerName,
          ),
          _InfoRow(
            icon: Icons.assignment_outlined,
            label: 'Reason',
            value: checkout.reason ?? '—',
          ),
          _InfoRow(
            icon: Icons.map_outlined,
            label: 'Zone',
            value: checkout.zoneName ?? '—',
          ),
          _InfoRow(
            icon: Icons.logout_rounded,
            label: 'Checked out',
            value: DateDisplay.dateTime(checkout.checkedOutAt),
          ),
          if (checkout.checkedOutByName != null)
            _InfoRow(
              icon: Icons.support_agent_outlined,
              label: 'Issued by',
              value: checkout.checkedOutByName!,
            ),
          _InfoRow(
            icon: Icons.schedule_outlined,
            label: 'Expected back',
            value: DateDisplay.dateTime(checkout.expectedReturnAt),
            emphasize: checkout.isOverdueReturn,
            last:
                (checkout.conditionOut == null ||
                    checkout.conditionOut!.isEmpty) &&
                (checkout.notes == null || checkout.notes!.isEmpty),
          ),
          if (checkout.conditionOut != null &&
              checkout.conditionOut!.isNotEmpty)
            _InfoRow(
              icon: Icons.description_outlined,
              label: 'Condition out',
              value: checkout.conditionOut!,
              last: checkout.notes == null || checkout.notes!.isEmpty,
            ),
          if (checkout.notes != null && checkout.notes!.isNotEmpty)
            _InfoRow(
              icon: Icons.sticky_note_2_outlined,
              label: 'Notes',
              value: checkout.notes!,
              last: true,
            ),
        ],
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({
    required this.title,
    required this.icon,
    required this.child,
    this.trailing,
  });

  final String title;
  final IconData icon;
  final Widget child;
  final String? trailing;

  @override
  Widget build(BuildContext context) {
    return GlassCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Icon(icon, size: 18, color: AppTheme.accent),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    color: AppTheme.text,
                    fontWeight: FontWeight.w700,
                    fontSize: 15,
                    letterSpacing: -0.2,
                  ),
                ),
              ),
              if (trailing != null)
                Text(
                  trailing!,
                  style: const TextStyle(
                    color: AppTheme.textMuted,
                    fontSize: 12,
                  ),
                ),
            ],
          ),
          const SizedBox(height: 14),
          child,
        ],
      ),
    );
  }
}

class _DueTile extends StatelessWidget {
  const _DueTile({
    required this.label,
    required this.value,
    required this.overdue,
    required this.dueSoon,
  });

  final String label;
  final String value;
  final bool overdue;
  final bool dueSoon;

  @override
  Widget build(BuildContext context) {
    final Color color = overdue
        ? AppTheme.danger
        : dueSoon
        ? AppTheme.warning
        : AppTheme.text;
    final String badge = overdue
        ? 'Overdue'
        : dueSoon
        ? 'Due soon'
        : 'On track';
    final Color badgeColor = overdue
        ? AppTheme.danger
        : dueSoon
        ? AppTheme.warning
        : AppTheme.success;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.04),
        borderRadius: BorderRadius.circular(AppTheme.radiusSmall),
        border: Border.all(color: badgeColor.withValues(alpha: 0.25)),
      ),
      child: Row(
        children: <Widget>[
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  label,
                  style: const TextStyle(
                    color: AppTheme.textMuted,
                    fontSize: 12.5,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  value,
                  style: TextStyle(
                    color: color,
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          StatusPill(label: badge, color: badgeColor),
        ],
      ),
    );
  }
}

class _ScheduleRow extends StatelessWidget {
  const _ScheduleRow({required this.schedule, required this.last});

  final EquipmentSchedule schedule;
  final bool last;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: <Widget>[
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 10),
          child: Row(
            children: <Widget>[
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      schedule.scheduleTypeLabel,
                      style: const TextStyle(
                        color: AppTheme.text,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    if (schedule.notes != null && schedule.notes!.isNotEmpty)
                      Text(
                        schedule.notes!,
                        style: const TextStyle(
                          color: AppTheme.textMuted,
                          fontSize: 12.5,
                        ),
                      ),
                  ],
                ),
              ),
              Text(
                'Every ${schedule.intervalDays}d',
                style: const TextStyle(
                  color: AppTheme.accent,
                  fontWeight: FontWeight.w600,
                  fontSize: 13,
                ),
              ),
            ],
          ),
        ),
        if (!last)
          Divider(height: 1, color: Colors.white.withValues(alpha: 0.06)),
      ],
    );
  }
}

class _HistoryRow extends StatelessWidget {
  const _HistoryRow({
    required this.title,
    required this.subtitle,
    required this.accent,
    required this.last,
    this.note,
  });

  final String title;
  final String subtitle;
  final String? note;
  final Color accent;
  final bool last;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: <Widget>[
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 10),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Container(
                width: 8,
                height: 8,
                margin: const EdgeInsets.only(top: 6),
                decoration: BoxDecoration(
                  color: accent,
                  shape: BoxShape.circle,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      title,
                      style: const TextStyle(
                        color: AppTheme.text,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    if (subtitle.isNotEmpty)
                      Text(
                        subtitle,
                        style: const TextStyle(
                          color: AppTheme.textMuted,
                          fontSize: 12.5,
                        ),
                      ),
                    if (note != null && note!.isNotEmpty)
                      Padding(
                        padding: const EdgeInsets.only(top: 4),
                        child: Text(
                          note!,
                          style: const TextStyle(
                            color: AppTheme.text,
                            fontSize: 13,
                            height: 1.35,
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
        if (!last)
          Divider(height: 1, color: Colors.white.withValues(alpha: 0.06)),
      ],
    );
  }
}

class _DocumentRow extends StatelessWidget {
  const _DocumentRow({required this.document, required this.last});

  final EquipmentDocumentItem document;
  final bool last;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: <Widget>[
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 10),
          child: Row(
            children: <Widget>[
              const Icon(
                Icons.picture_as_pdf_outlined,
                color: AppTheme.textMuted,
                size: 20,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      document.title,
                      style: const TextStyle(
                        color: AppTheme.text,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    Text(
                      DateDisplay.date(document.createdAt),
                      style: const TextStyle(
                        color: AppTheme.textMuted,
                        fontSize: 12.5,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        if (!last)
          Divider(height: 1, color: Colors.white.withValues(alpha: 0.06)),
      ],
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({
    required this.icon,
    required this.label,
    required this.value,
    this.emphasize = false,
    this.copyable = false,
    this.last = false,
  });

  final IconData icon;
  final String label;
  final String value;
  final bool emphasize;
  final bool copyable;
  final bool last;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: <Widget>[
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 10),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Icon(icon, size: 18, color: AppTheme.textMuted),
              const SizedBox(width: 12),
              SizedBox(
                width: 108,
                child: Text(
                  label,
                  style: const TextStyle(
                    color: AppTheme.textMuted,
                    fontSize: 13,
                  ),
                ),
              ),
              Expanded(
                child: Text(
                  value,
                  style: TextStyle(
                    color: emphasize ? AppTheme.danger : AppTheme.text,
                    fontWeight: emphasize ? FontWeight.w700 : FontWeight.w500,
                  ),
                ),
              ),
              if (copyable)
                IconButton(
                  visualDensity: VisualDensity.compact,
                  tooltip: 'Copy',
                  onPressed: () async {
                    await Clipboard.setData(ClipboardData(text: value));
                    if (context.mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Copied to clipboard')),
                      );
                    }
                  },
                  icon: const Icon(Icons.copy_rounded, size: 16),
                ),
            ],
          ),
        ),
        if (!last)
          Divider(height: 1, color: Colors.white.withValues(alpha: 0.06)),
      ],
    );
  }
}

class _NoticeCard extends StatelessWidget {
  const _NoticeCard({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return GlassCard(
      padding: const EdgeInsets.all(16),
      child: Row(
        children: <Widget>[
          Icon(icon, color: AppTheme.textMuted, size: 20),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              text,
              style: const TextStyle(color: AppTheme.textMuted, height: 1.4),
            ),
          ),
        ],
      ),
    );
  }
}

class _ErrorView extends StatelessWidget {
  const _ErrorView({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: GlassCard(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Container(
                width: 60,
                height: 60,
                decoration: BoxDecoration(
                  color: AppTheme.danger.withValues(alpha: 0.16),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.error_outline,
                  color: AppTheme.danger,
                  size: 30,
                ),
              ),
              const SizedBox(height: 16),
              Text(
                message,
                textAlign: TextAlign.center,
                style: const TextStyle(color: AppTheme.text, height: 1.4),
              ),
              const SizedBox(height: 20),
              GlossyButton(
                label: 'Try again',
                icon: Icons.refresh_rounded,
                onPressed: onRetry,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

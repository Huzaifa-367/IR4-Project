import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ir4_mobile/core/theme/app_theme.dart';
import 'package:ir4_mobile/core/widgets/glossy_button.dart';
import 'package:ir4_mobile/features/equipment/domain/equipment_models.dart';
import 'package:ir4_mobile/features/equipment/presentation/equipment_controller.dart';
import 'package:ir4_mobile/features/equipment/presentation/sheet_scaffold.dart';

Future<bool> showReturnSheet({
  required BuildContext context,
  required WidgetRef ref,
}) async {
  final bool? success = await showModalBottomSheet<bool>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (BuildContext sheetContext) => _ReturnSheet(ref: ref),
  );
  return success ?? false;
}

const Map<String, ({String label, IconData icon, Color color})> _statuses =
    <String, ({String label, IconData icon, Color color})>{
  'ok': (label: 'OK', icon: Icons.check_circle_outline, color: AppTheme.success),
  'damaged': (
    label: 'Damaged',
    icon: Icons.report_gmailerrorred_outlined,
    color: AppTheme.danger,
  ),
  'needs_service': (
    label: 'Needs service',
    icon: Icons.build_circle_outlined,
    color: AppTheme.warning,
  ),
};

class _ReturnSheet extends StatefulWidget {
  const _ReturnSheet({required this.ref});

  final WidgetRef ref;

  @override
  State<_ReturnSheet> createState() => _ReturnSheetState();
}

class _ReturnSheetState extends State<_ReturnSheet> {
  String _status = 'ok';
  final TextEditingController _reason = TextEditingController();
  final TextEditingController _condition = TextEditingController();
  bool _submitting = false;

  @override
  void dispose() {
    _reason.dispose();
    _condition.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() => _submitting = true);
    final bool ok = await widget.ref
        .read(equipmentControllerProvider.notifier)
        .returnItem(
          ReturnRequest(
            returnStatus: _status,
            returnReason:
                _reason.text.trim().isEmpty ? null : _reason.text.trim(),
            conditionIn:
                _condition.text.trim().isEmpty ? null : _condition.text.trim(),
          ),
        );
    if (mounted) {
      Navigator.of(context).pop(ok);
    }
  }

  @override
  Widget build(BuildContext context) {
    return SheetScaffold(
      title: 'Return equipment',
      icon: Icons.assignment_return_rounded,
      children: <Widget>[
        const SheetLabel('Return status'),
        const SizedBox(height: 10),
        Row(
          children: _statuses.entries
              .map(
                (entry) => Expanded(
                  child: Padding(
                    padding: EdgeInsets.only(
                      right: entry.key == 'needs_service' ? 0 : 8,
                    ),
                    child: _StatusChip(
                      label: entry.value.label,
                      icon: entry.value.icon,
                      color: entry.value.color,
                      selected: _status == entry.key,
                      onTap: _submitting
                          ? null
                          : () => setState(() => _status = entry.key),
                    ),
                  ),
                ),
              )
              .toList(),
        ),
        const SizedBox(height: 18),
        const SheetLabel('Return reason (optional)'),
        const SizedBox(height: 8),
        TextField(
          controller: _reason,
          enabled: !_submitting,
          decoration: const InputDecoration(
            hintText: 'Why is it coming back?',
            prefixIcon: Icon(Icons.assignment_outlined),
          ),
        ),
        const SizedBox(height: 18),
        const SheetLabel('Condition in (optional)'),
        const SizedBox(height: 8),
        TextField(
          controller: _condition,
          enabled: !_submitting,
          decoration: const InputDecoration(
            hintText: 'Any damage or notes',
            prefixIcon: Icon(Icons.description_outlined),
          ),
        ),
        const SizedBox(height: 24),
        GlossyButton(
          label: 'Confirm return',
          icon: Icons.check_rounded,
          variant: GlossyVariant.warning,
          loading: _submitting,
          onPressed: _submitting ? null : _submit,
        ),
      ],
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({
    required this.label,
    required this.icon,
    required this.color,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final Color color;
  final bool selected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        padding: const EdgeInsets.symmetric(vertical: 14),
        decoration: BoxDecoration(
          color: selected
              ? color.withValues(alpha: 0.18)
              : Colors.white.withValues(alpha: 0.05),
          borderRadius: BorderRadius.circular(AppTheme.radiusSmall),
          border: Border.all(
            color: selected ? color : Colors.white.withValues(alpha: 0.12),
            width: selected ? 1.5 : 1,
          ),
        ),
        child: Column(
          children: <Widget>[
            Icon(icon, color: selected ? color : AppTheme.textMuted, size: 22),
            const SizedBox(height: 6),
            Text(
              label,
              textAlign: TextAlign.center,
              style: TextStyle(
                color: selected ? color : AppTheme.textMuted,
                fontSize: 12,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

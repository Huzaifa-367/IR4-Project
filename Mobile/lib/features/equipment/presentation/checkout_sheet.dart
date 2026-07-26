import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ir4_mobile/core/theme/app_theme.dart';
import 'package:ir4_mobile/core/widgets/glossy_button.dart';
import 'package:ir4_mobile/features/equipment/domain/equipment_models.dart';
import 'package:ir4_mobile/features/equipment/presentation/equipment_controller.dart';
import 'package:ir4_mobile/features/equipment/presentation/sheet_scaffold.dart';

Future<bool> showCheckoutSheet({
  required BuildContext context,
  required WidgetRef ref,
  required EquipmentScanResult scan,
}) async {
  final bool? success = await showModalBottomSheet<bool>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (BuildContext sheetContext) {
      return _CheckoutSheet(ref: ref, scan: scan);
    },
  );
  return success ?? false;
}

class _CheckoutSheet extends StatefulWidget {
  const _CheckoutSheet({required this.ref, required this.scan});

  final WidgetRef ref;
  final EquipmentScanResult scan;

  @override
  State<_CheckoutSheet> createState() => _CheckoutSheetState();
}

class _CheckoutSheetState extends State<_CheckoutSheet> {
  late NamedRef? _worker = widget.scan.workers.isEmpty
      ? null
      : widget.scan.workers.first;
  NamedRef? _zone;
  final TextEditingController _reason = TextEditingController();
  final TextEditingController _condition = TextEditingController();
  DateTime? _expectedReturnAt;
  bool _submitting = false;

  @override
  void dispose() {
    _reason.dispose();
    _condition.dispose();
    super.dispose();
  }

  Future<void> _pickExpectedReturn() async {
    final DateTime now = DateTime.now();
    final DateTime initialDate =
        _expectedReturnAt ?? now.add(const Duration(hours: 8));
    final DateTime? date = await showDatePicker(
      context: context,
      initialDate: initialDate.isBefore(now) ? now : initialDate,
      firstDate: now,
      lastDate: now.add(const Duration(days: 365 * 2)),
      builder: (BuildContext context, Widget? child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.dark(
              primary: AppTheme.accent,
              surface: AppTheme.surface2,
              onSurface: AppTheme.text,
            ),
          ),
          child: child!,
        );
      },
    );
    if (date == null || !mounted) {
      return;
    }
    final TimeOfDay? time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(_expectedReturnAt ?? initialDate),
      builder: (BuildContext context, Widget? child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.dark(
              primary: AppTheme.accent,
              surface: AppTheme.surface2,
              onSurface: AppTheme.text,
            ),
          ),
          child: child!,
        );
      },
    );
    if (time == null || !mounted) {
      return;
    }
    setState(() {
      _expectedReturnAt = DateTime(
        date.year,
        date.month,
        date.day,
        time.hour,
        time.minute,
      );
    });
  }

  String _formatExpectedReturn(DateTime value) {
    final String y = value.year.toString().padLeft(4, '0');
    final String m = value.month.toString().padLeft(2, '0');
    final String d = value.day.toString().padLeft(2, '0');
    final String h = value.hour.toString().padLeft(2, '0');
    final String min = value.minute.toString().padLeft(2, '0');
    return '$y-$m-$d $h:$min';
  }

  String _toApiDateTime(DateTime value) {
    return value.toUtc().toIso8601String();
  }

  Future<void> _submit() async {
    setState(() => _submitting = true);
    final bool ok = await widget.ref
        .read(equipmentControllerProvider.notifier)
        .checkout(
          CheckoutRequest(
            workerId: _worker!.id,
            reason: _reason.text.trim(),
            zoneId: _zone?.id,
            expectedReturnAt: _expectedReturnAt == null
                ? null
                : _toApiDateTime(_expectedReturnAt!),
            conditionOut: _condition.text.trim().isEmpty
                ? null
                : _condition.text.trim(),
          ),
        );
    if (mounted) {
      Navigator.of(context).pop(ok);
    }
  }

  @override
  Widget build(BuildContext context) {
    return SheetScaffold(
      title: 'Check out equipment',
      icon: Icons.logout_rounded,
      children: <Widget>[
        const SheetLabel('Worker'),
        const SizedBox(height: 8),
        DropdownButtonFormField<NamedRef>(
          initialValue: _worker,
          isExpanded: true,
          borderRadius: BorderRadius.circular(AppTheme.radiusSmall),
          dropdownColor: AppTheme.surface2,
          items: widget.scan.workers
              .map(
                (NamedRef worker) => DropdownMenuItem<NamedRef>(
                  value: worker,
                  child: Text(worker.name),
                ),
              )
              .toList(),
          onChanged: _submitting
              ? null
              : (NamedRef? value) => setState(() => _worker = value),
          decoration: const InputDecoration(
            prefixIcon: Icon(Icons.badge_outlined),
          ),
        ),
        const SizedBox(height: 18),
        const SheetLabel('Reason / task'),
        const SizedBox(height: 8),
        TextField(
          controller: _reason,
          enabled: !_submitting,
          decoration: const InputDecoration(
            hintText: 'What is it being used for?',
            prefixIcon: Icon(Icons.assignment_outlined),
          ),
        ),
        const SizedBox(height: 18),
        const SheetLabel('Zone (optional)'),
        const SizedBox(height: 8),
        DropdownButtonFormField<NamedRef?>(
          initialValue: _zone,
          isExpanded: true,
          borderRadius: BorderRadius.circular(AppTheme.radiusSmall),
          dropdownColor: AppTheme.surface2,
          items: <DropdownMenuItem<NamedRef?>>[
            const DropdownMenuItem<NamedRef?>(
              value: null,
              child: Text('No zone'),
            ),
            ...widget.scan.zones.map(
              (NamedRef zone) => DropdownMenuItem<NamedRef?>(
                value: zone,
                child: Text(zone.name),
              ),
            ),
          ],
          onChanged: _submitting
              ? null
              : (NamedRef? value) => setState(() => _zone = value),
          decoration: const InputDecoration(
            prefixIcon: Icon(Icons.map_outlined),
          ),
        ),
        const SizedBox(height: 18),
        const SheetLabel('Expected return (optional)'),
        const SizedBox(height: 8),
        InkWell(
          onTap: _submitting ? null : _pickExpectedReturn,
          borderRadius: BorderRadius.circular(AppTheme.radiusSmall),
          child: InputDecorator(
            decoration: InputDecoration(
              prefixIcon: const Icon(Icons.schedule_outlined),
              suffixIcon: _expectedReturnAt == null
                  ? const Icon(Icons.calendar_month_outlined)
                  : IconButton(
                      tooltip: 'Clear',
                      onPressed: _submitting
                          ? null
                          : () => setState(() => _expectedReturnAt = null),
                      icon: const Icon(Icons.close_rounded),
                    ),
            ),
            child: Text(
              _expectedReturnAt == null
                  ? 'Pick date & time'
                  : _formatExpectedReturn(_expectedReturnAt!),
              style: TextStyle(
                color: _expectedReturnAt == null
                    ? const Color(0xFF6C7789)
                    : AppTheme.text,
                fontSize: 16,
              ),
            ),
          ),
        ),
        const SizedBox(height: 18),
        const SheetLabel('Condition out (optional)'),
        const SizedBox(height: 8),
        TextField(
          controller: _condition,
          enabled: !_submitting,
          decoration: const InputDecoration(
            hintText: 'Any notes on current condition',
            prefixIcon: Icon(Icons.description_outlined),
          ),
        ),
        const SizedBox(height: 24),
        GlossyButton(
          label: 'Confirm checkout',
          icon: Icons.check_rounded,
          loading: _submitting,
          onPressed: (_submitting || _worker == null) ? null : _submit,
        ),
      ],
    );
  }
}

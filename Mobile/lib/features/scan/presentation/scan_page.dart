import 'package:auto_route/auto_route.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ir4_mobile/core/router/app_router.dart';
import 'package:ir4_mobile/core/theme/app_theme.dart';
import 'package:ir4_mobile/core/widgets/glass_card.dart';
import 'package:ir4_mobile/core/widgets/glossy_button.dart';
import 'package:ir4_mobile/core/widgets/scan_overlay.dart';
import 'package:ir4_mobile/features/auth/presentation/auth_controller.dart';
import 'package:ir4_mobile/features/scan/equipment_qr.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

@RoutePage()
class ScanPage extends ConsumerStatefulWidget {
  const ScanPage({super.key});

  @override
  ConsumerState<ScanPage> createState() => _ScanPageState();
}

class _ScanPageState extends ConsumerState<ScanPage> {
  final MobileScannerController _scannerController = MobileScannerController(
    detectionSpeed: DetectionSpeed.normal,
    facing: CameraFacing.back,
    formats: <BarcodeFormat>[BarcodeFormat.qrCode],
  );
  final TextEditingController _manualController = TextEditingController();
  bool _handlingScan = false;
  bool _torchOn = false;
  bool _manualOpen = false;
  String? _manualError;

  @override
  void dispose() {
    _scannerController.dispose();
    _manualController.dispose();
    super.dispose();
  }

  Future<void> _handleRaw(String raw) async {
    if (_handlingScan) {
      return;
    }
    final String? token = parseEquipmentQrToken(raw);
    if (token == null) {
      setState(() {
        _manualError = 'Enter a valid QR token (UUID) or a public /e/{token} URL.';
      });
      return;
    }
    setState(() {
      _handlingScan = true;
      _manualError = null;
    });
    await _scannerController.stop();
    if (!mounted) {
      return;
    }
    await context.router.push(EquipmentDetailRoute(qrToken: token));
    if (!mounted) {
      return;
    }
    setState(() => _handlingScan = false);
    await _scannerController.start();
  }

  Future<void> _toggleTorch() async {
    await _scannerController.toggleTorch();
    if (mounted) {
      setState(() => _torchOn = !_torchOn);
    }
  }

  @override
  Widget build(BuildContext context) {
    final EdgeInsets padding = MediaQuery.paddingOf(context);
    return Scaffold(
      backgroundColor: AppTheme.background,
      body: Stack(
        fit: StackFit.expand,
        children: <Widget>[
          MobileScanner(
            controller: _scannerController,
            onDetect: (BarcodeCapture capture) {
              final String? value = capture.barcodes
                  .map((Barcode barcode) => barcode.rawValue)
                  .whereType<String>()
                  .firstOrNull;
              if (value != null) {
                _handleRaw(value);
              }
            },
          ),
          const ScanOverlay(),
          _buildTopBar(padding),
          _buildInstruction(),
          _buildManualPanel(padding),
          if (_handlingScan)
            const ColoredBox(
              color: Color(0xAA05070C),
              child: Center(child: CircularProgressIndicator()),
            ),
        ],
      ),
    );
  }

  Widget _buildTopBar(EdgeInsets padding) {
    return Positioned(
      top: padding.top + 12,
      left: 16,
      right: 16,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: <Widget>[
          const Text(
            'Scan equipment',
            style: TextStyle(
              color: Colors.white,
              fontSize: 20,
              fontWeight: FontWeight.w700,
              letterSpacing: -0.4,
              shadows: <Shadow>[Shadow(color: Colors.black54, blurRadius: 8)],
            ),
          ),
          Row(
            children: <Widget>[
              _CircleAction(
                icon: _torchOn ? Icons.flash_on_rounded : Icons.flash_off_rounded,
                active: _torchOn,
                onTap: _toggleTorch,
              ),
              const SizedBox(width: 10),
              _CircleAction(
                icon: Icons.logout_rounded,
                onTap: () =>
                    ref.read(authControllerProvider.notifier).logout(),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildInstruction() {
    return Align(
      alignment: const Alignment(0, 0.34),
      child: Text(
        'Align the QR label within the frame',
        style: TextStyle(
          color: Colors.white.withValues(alpha: 0.85),
          fontSize: 14,
          fontWeight: FontWeight.w500,
          shadows: const <Shadow>[Shadow(color: Colors.black54, blurRadius: 8)],
        ),
      ),
    );
  }

  Widget _buildManualPanel(EdgeInsets padding) {
    return Positioned(
      left: 16,
      right: 16,
      bottom: padding.bottom + 20,
      child: GlassCard(
        padding: const EdgeInsets.all(18),
        child: AnimatedSize(
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOut,
          child: _manualOpen ? _buildManualForm() : _buildManualToggle(),
        ),
      ),
    );
  }

  Widget _buildManualToggle() {
    return Row(
      children: <Widget>[
        Container(
          width: 42,
          height: 42,
          decoration: BoxDecoration(
            color: AppTheme.accent.withValues(alpha: 0.16),
            borderRadius: BorderRadius.circular(12),
          ),
          child: const Icon(Icons.keyboard_alt_outlined, color: AppTheme.accent),
        ),
        const SizedBox(width: 14),
        const Expanded(
          child: Text(
            "Can't scan a damaged label?",
            style: TextStyle(color: AppTheme.text, fontWeight: FontWeight.w600),
          ),
        ),
        TextButton(
          onPressed: () => setState(() => _manualOpen = true),
          child: const Text('Enter code'),
        ),
      ],
    );
  }

  Widget _buildManualForm() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        Row(
          children: <Widget>[
            const Expanded(
              child: Text(
                'Enter token manually',
                style: TextStyle(
                  color: AppTheme.text,
                  fontWeight: FontWeight.w600,
                  fontSize: 15,
                ),
              ),
            ),
            IconButton(
              onPressed: () => setState(() {
                _manualOpen = false;
                _manualError = null;
              }),
              icon: const Icon(Icons.close_rounded, color: AppTheme.textMuted),
            ),
          ],
        ),
        const SizedBox(height: 6),
        TextField(
          controller: _manualController,
          enabled: !_handlingScan,
          decoration: const InputDecoration(
            hintText: 'UUID or https://…/e/{token}',
            prefixIcon: Icon(Icons.tag_rounded),
          ),
        ),
        if (_manualError != null) ...<Widget>[
          const SizedBox(height: 10),
          Text(
            _manualError!,
            style: const TextStyle(color: AppTheme.danger, fontSize: 13),
          ),
        ],
        const SizedBox(height: 14),
        GlossyButton(
          label: 'Look up',
          icon: Icons.search_rounded,
          onPressed: _handlingScan
              ? null
              : () => _handleRaw(_manualController.text),
        ),
      ],
    );
  }
}

class _CircleAction extends StatelessWidget {
  const _CircleAction({
    required this.icon,
    required this.onTap,
    this.active = false,
  });

  final IconData icon;
  final VoidCallback onTap;
  final bool active;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 44,
        height: 44,
        decoration: BoxDecoration(
          color: active
              ? AppTheme.accent.withValues(alpha: 0.9)
              : Colors.black.withValues(alpha: 0.4),
          shape: BoxShape.circle,
          border: Border.all(color: Colors.white.withValues(alpha: 0.25)),
        ),
        child: Icon(icon, color: Colors.white, size: 22),
      ),
    );
  }
}

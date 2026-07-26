import 'package:flutter/material.dart';
import 'package:ir4_mobile/core/theme/app_theme.dart';

/// Dims the camera feed and cuts out a rounded viewfinder with glowing corner
/// brackets plus an animated sweep line.
class ScanOverlay extends StatefulWidget {
  const ScanOverlay({super.key, this.cutoutSize = 260});

  final double cutoutSize;

  @override
  State<ScanOverlay> createState() => _ScanOverlayState();
}

class _ScanOverlayState extends State<ScanOverlay>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 2200),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: AnimatedBuilder(
        animation: _controller,
        builder: (BuildContext context, Widget? child) {
          return CustomPaint(
            painter: _ScanPainter(
              cutoutSize: widget.cutoutSize,
              sweep: _controller.value,
            ),
            size: Size.infinite,
          );
        },
      ),
    );
  }
}

class _ScanPainter extends CustomPainter {
  _ScanPainter({required this.cutoutSize, required this.sweep});

  final double cutoutSize;
  final double sweep;

  @override
  void paint(Canvas canvas, Size size) {
    final Rect full = Offset.zero & size;
    final Rect cutout = Rect.fromCenter(
      center: size.center(const Offset(0, -30)),
      width: cutoutSize,
      height: cutoutSize,
    );
    final RRect rounded = RRect.fromRectAndRadius(
      cutout,
      const Radius.circular(28),
    );

    final Path mask = Path.combine(
      PathOperation.difference,
      Path()..addRect(full),
      Path()..addRRect(rounded),
    );
    canvas.drawPath(mask, Paint()..color = Colors.black.withValues(alpha: 0.55));

    final Paint bracket = Paint()
      ..color = AppTheme.accent
      ..strokeWidth = 4
      ..strokeCap = StrokeCap.round
      ..style = PaintingStyle.stroke;
    const double arm = 30;
    const double r = 28;
    // Top-left.
    canvas.drawPath(
      Path()
        ..moveTo(cutout.left, cutout.top + arm)
        ..lineTo(cutout.left, cutout.top + r)
        ..arcToPoint(Offset(cutout.left + r, cutout.top), radius: const Radius.circular(r))
        ..lineTo(cutout.left + arm, cutout.top),
      bracket,
    );
    // Top-right.
    canvas.drawPath(
      Path()
        ..moveTo(cutout.right - arm, cutout.top)
        ..lineTo(cutout.right - r, cutout.top)
        ..arcToPoint(Offset(cutout.right, cutout.top + r), radius: const Radius.circular(r))
        ..lineTo(cutout.right, cutout.top + arm),
      bracket,
    );
    // Bottom-right.
    canvas.drawPath(
      Path()
        ..moveTo(cutout.right, cutout.bottom - arm)
        ..lineTo(cutout.right, cutout.bottom - r)
        ..arcToPoint(Offset(cutout.right - r, cutout.bottom), radius: const Radius.circular(r))
        ..lineTo(cutout.right - arm, cutout.bottom),
      bracket,
    );
    // Bottom-left.
    canvas.drawPath(
      Path()
        ..moveTo(cutout.left + arm, cutout.bottom)
        ..lineTo(cutout.left + r, cutout.bottom)
        ..arcToPoint(Offset(cutout.left, cutout.bottom - r), radius: const Radius.circular(r))
        ..lineTo(cutout.left, cutout.bottom - arm),
      bracket,
    );

    final double lineY = cutout.top + 12 + (cutout.height - 24) * sweep;
    final Paint sweepPaint = Paint()
      ..shader = LinearGradient(
        colors: <Color>[
          AppTheme.accent.withValues(alpha: 0),
          AppTheme.accent.withValues(alpha: 0.9),
          AppTheme.accent.withValues(alpha: 0),
        ],
      ).createShader(Rect.fromLTWH(cutout.left, lineY, cutout.width, 2));
    canvas.drawRect(
      Rect.fromLTWH(cutout.left + 8, lineY, cutout.width - 16, 2),
      sweepPaint,
    );
  }

  @override
  bool shouldRepaint(_ScanPainter oldDelegate) => oldDelegate.sweep != sweep;
}

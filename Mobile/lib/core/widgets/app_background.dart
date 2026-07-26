import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:ir4_mobile/core/theme/app_theme.dart';

/// Full-screen gradient backdrop with soft, blurred colour blobs — the base
/// layer every glass surface floats above.
class AppBackground extends StatelessWidget {
  const AppBackground({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: const BoxDecoration(gradient: AppTheme.backdropGradient),
      child: Stack(
        children: <Widget>[
          const Positioned(
            top: -140,
            right: -100,
            child: _Blob(color: AppTheme.accent, size: 340),
          ),
          const Positioned(
            bottom: -160,
            left: -120,
            child: _Blob(color: AppTheme.accentAlt, size: 380),
          ),
          Positioned.fill(child: child),
        ],
      ),
    );
  }
}

class _Blob extends StatelessWidget {
  const _Blob({required this.color, required this.size});

  final Color color;
  final double size;

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: ImageFiltered(
        imageFilter: ImageFilter.blur(sigmaX: 90, sigmaY: 90),
        child: Container(
          width: size,
          height: size,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            gradient: RadialGradient(
              colors: <Color>[color.withValues(alpha: 0.45), color.withValues(alpha: 0)],
            ),
          ),
        ),
      ),
    );
  }
}

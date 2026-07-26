import 'package:flutter/material.dart';
import 'package:ir4_mobile/core/theme/app_theme.dart';

enum GlossyVariant { primary, warning, danger }

/// Gradient-filled action button with an iOS-style glossy top sheen, press
/// scale feedback, and an inline loading state.
class GlossyButton extends StatefulWidget {
  const GlossyButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon,
    this.variant = GlossyVariant.primary,
    this.loading = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final GlossyVariant variant;
  final bool loading;

  @override
  State<GlossyButton> createState() => _GlossyButtonState();
}

class _GlossyButtonState extends State<GlossyButton> {
  bool _pressed = false;

  bool get _enabled => widget.onPressed != null && !widget.loading;

  LinearGradient get _gradient {
    switch (widget.variant) {
      case GlossyVariant.primary:
        return AppTheme.primaryGradient;
      case GlossyVariant.warning:
        return AppTheme.warningGradient;
      case GlossyVariant.danger:
        return AppTheme.dangerGradient;
    }
  }

  Color get _glowColor {
    switch (widget.variant) {
      case GlossyVariant.primary:
        return AppTheme.accent;
      case GlossyVariant.warning:
        return AppTheme.warning;
      case GlossyVariant.danger:
        return AppTheme.danger;
    }
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTapDown: _enabled ? (_) => setState(() => _pressed = true) : null,
      onTapUp: _enabled ? (_) => setState(() => _pressed = false) : null,
      onTapCancel: _enabled ? () => setState(() => _pressed = false) : null,
      onTap: _enabled ? widget.onPressed : null,
      child: AnimatedScale(
        scale: _pressed ? 0.97 : 1,
        duration: const Duration(milliseconds: 120),
        curve: Curves.easeOut,
        child: AnimatedOpacity(
          opacity: _enabled ? 1 : 0.55,
          duration: const Duration(milliseconds: 150),
          child: Container(
            height: 56,
            decoration: BoxDecoration(
              gradient: _gradient,
              borderRadius: BorderRadius.circular(AppTheme.radiusMedium),
              border: Border.all(color: Colors.white.withValues(alpha: 0.25)),
              boxShadow: <BoxShadow>[
                BoxShadow(
                  color: _glowColor.withValues(alpha: 0.45),
                  blurRadius: 24,
                  offset: const Offset(0, 10),
                ),
              ],
            ),
            child: Stack(
              children: <Widget>[
                Positioned(
                  top: 0,
                  left: 0,
                  right: 0,
                  height: 26,
                  child: Container(
                    decoration: BoxDecoration(
                      borderRadius: const BorderRadius.vertical(
                        top: Radius.circular(AppTheme.radiusMedium),
                      ),
                      gradient: LinearGradient(
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                        colors: <Color>[
                          Colors.white.withValues(alpha: 0.35),
                          Colors.white.withValues(alpha: 0),
                        ],
                      ),
                    ),
                  ),
                ),
                Center(child: _buildContent()),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildContent() {
    if (widget.loading) {
      return const SizedBox(
        width: 22,
        height: 22,
        child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white),
      );
    }
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        if (widget.icon != null) ...<Widget>[
          Icon(widget.icon, color: Colors.white, size: 20),
          const SizedBox(width: 8),
        ],
        Text(
          widget.label,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 16,
            fontWeight: FontWeight.w600,
            letterSpacing: -0.2,
          ),
        ),
      ],
    );
  }
}

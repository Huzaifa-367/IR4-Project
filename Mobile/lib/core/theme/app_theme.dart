import 'package:flutter/material.dart';

/// iOS 26 "liquid glass" inspired design tokens: deep gradient backdrops,
/// translucent frosted surfaces, vivid system-style accents.
abstract final class AppTheme {
  static const Color background = Color(0xFF05070C);
  static const Color backgroundHigh = Color(0xFF0D1526);
  static const Color surface = Color(0xFF121A28);
  static const Color surface2 = Color(0xFF182233);
  static const Color border = Color(0x1FFFFFFF);
  static const Color borderStrong = Color(0x33FFFFFF);
  static const Color text = Color(0xFFF3F6FB);
  static const Color textMuted = Color(0xFF9AA7B8);

  static const Color accent = Color(0xFF0A84FF);
  static const Color accentAlt = Color(0xFF5E5CE6);
  static const Color danger = Color(0xFFFF453A);
  static const Color success = Color(0xFF30D158);
  static const Color warning = Color(0xFFFF9F0A);

  static const double radiusLarge = 28;
  static const double radiusMedium = 20;
  static const double radiusSmall = 14;

  static const LinearGradient backdropGradient = LinearGradient(
    begin: Alignment.topCenter,
    end: Alignment.bottomCenter,
    colors: <Color>[Color(0xFF0A1120), background],
  );

  static const LinearGradient primaryGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[Color(0xFF3AA0FF), accent, Color(0xFF0A6BFF)],
  );

  static const LinearGradient warningGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[Color(0xFFFFC24B), warning, Color(0xFFF08A00)],
  );

  static const LinearGradient dangerGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[Color(0xFFFF6A61), danger, Color(0xFFD70015)],
  );

  static const LinearGradient glassGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[Color(0x1FFFFFFF), Color(0x0AFFFFFF)],
  );

  static ThemeData dark() {
    final ColorScheme scheme = ColorScheme.fromSeed(
      seedColor: accent,
      brightness: Brightness.dark,
      surface: surface,
    );
    final ThemeData base = ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      fontFamily: 'SF Pro Text',
    );
    return base.copyWith(
      colorScheme: scheme.copyWith(
        primary: accent,
        secondary: accentAlt,
        error: danger,
        surface: surface,
      ),
      scaffoldBackgroundColor: background,
      textTheme: _textTheme(base.textTheme),
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.transparent,
        surfaceTintColor: Colors.transparent,
        foregroundColor: text,
        elevation: 0,
        centerTitle: true,
        titleTextStyle: TextStyle(
          color: text,
          fontSize: 17,
          fontWeight: FontWeight.w600,
          letterSpacing: -0.2,
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: const Color(0x14FFFFFF),
        contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(radiusSmall),
          borderSide: BorderSide.none,
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(radiusSmall),
          borderSide: const BorderSide(color: border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(radiusSmall),
          borderSide: const BorderSide(color: accent, width: 1.5),
        ),
        labelStyle: const TextStyle(color: textMuted),
        hintStyle: const TextStyle(color: Color(0xFF6C7789)),
        floatingLabelStyle: const TextStyle(color: accent),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: surface2,
        contentTextStyle: const TextStyle(color: text),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(radiusSmall),
          side: const BorderSide(color: border),
        ),
      ),
    );
  }

  static TextTheme _textTheme(TextTheme base) {
    return base
        .apply(bodyColor: text, displayColor: text)
        .copyWith(
          headlineMedium: const TextStyle(
            fontWeight: FontWeight.w700,
            letterSpacing: -0.6,
            color: text,
          ),
          titleLarge: const TextStyle(
            fontWeight: FontWeight.w700,
            letterSpacing: -0.4,
            color: text,
          ),
          bodyMedium: const TextStyle(color: text, letterSpacing: -0.1),
        );
  }
}

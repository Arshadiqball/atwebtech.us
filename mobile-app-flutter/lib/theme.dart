import 'package:flutter/material.dart';

/// Colors that mirror the website's theme tokens (public/assets/css/theme.css).
@immutable
class AppColors extends ThemeExtension<AppColors> {
  const AppColors({
    required this.bg, required this.canvas, required this.panel, required this.soft, required this.line, required this.ink, required this.ink2, required this.muted,
    required this.green, required this.brandInk, required this.greenInk, required this.greenSoft,
    required this.bad, required this.badBg, required this.badInk, required this.warnBg, required this.warnInk, required this.infoBg, required this.infoInk,
  });

  final Color bg, canvas, panel, soft, line, ink, ink2, muted;
  final Color green, brandInk, greenInk, greenSoft;
  final Color bad, badBg, badInk, warnBg, warnInk, infoBg, infoInk;

  static const light = AppColors(
    bg: Color(0xFFF1F0EB), canvas: Color(0xFFFAFAF8), panel: Color(0xFFFFFFFF), soft: Color(0xFFF4F4F0), line: Color(0xFFE4E3DD),
    ink: Color(0xFF16201E), ink2: Color(0xFF3D4846), muted: Color(0xFF69716D),
    green: Color(0xFFB0EC9C), brandInk: Color(0xFF14301C), greenInk: Color(0xFF256339), greenSoft: Color(0xFFE3F5DB),
    bad: Color(0xFFD64535), badBg: Color(0xFFFDECEA), badInk: Color(0xFF8F1F15), warnBg: Color(0xFFFFF4DC), warnInk: Color(0xFF6B4300),
    infoBg: Color(0xFFE6F0FB), infoInk: Color(0xFF1D4A73),
  );

  static const dark = AppColors(
    bg: Color(0xFF0C100F), canvas: Color(0xFF111615), panel: Color(0xFF181E1D), soft: Color(0xFF1F2625), line: Color(0xFF283130),
    ink: Color(0xFFEEF1EE), ink2: Color(0xFFC3CAC6), muted: Color(0xFF8D9793),
    green: Color(0xFFB0EC9C), brandInk: Color(0xFF10281A), greenInk: Color(0xFFB4EBBF), greenSoft: Color(0xFF1A3324),
    bad: Color(0xFFF0685A), badBg: Color(0xFF3A1D1A), badInk: Color(0xFFFFC4BC), warnBg: Color(0xFF33290F), warnInk: Color(0xFFF4DDA6),
    infoBg: Color(0xFF16283A), infoInk: Color(0xFFB9D8F5),
  );

  @override
  AppColors copyWith() => this;

  @override
  AppColors lerp(ThemeExtension<AppColors>? other, double t) => other is AppColors && t > 0.5 ? other : this;
}

extension AppTheme on BuildContext {
  AppColors get c => Theme.of(this).extension<AppColors>()!;
}

ThemeData buildTheme(Brightness brightness) {
  final c = brightness == Brightness.dark ? AppColors.dark : AppColors.light;
  final scheme = ColorScheme(
    brightness: brightness,
    primary: c.greenInk, onPrimary: brightness == Brightness.dark ? c.brandInk : Colors.white,
    secondary: c.green, onSecondary: c.brandInk,
    error: c.bad, onError: Colors.white,
    surface: c.canvas, onSurface: c.ink,
    surfaceContainerLowest: c.panel, surfaceContainerLow: c.panel, surfaceContainer: c.panel, surfaceContainerHigh: c.soft, surfaceContainerHighest: c.soft,
    onSurfaceVariant: c.ink2, outline: c.line, outlineVariant: c.line,
    secondaryContainer: c.greenSoft, onSecondaryContainer: c.greenInk,
    primaryContainer: c.greenSoft, onPrimaryContainer: c.greenInk,
  );
  final radius = BorderRadius.circular(12);
  return ThemeData(
    useMaterial3: true,
    brightness: brightness,
    colorScheme: scheme,
    scaffoldBackgroundColor: c.canvas,
    extensions: [c],
    dividerTheme: DividerThemeData(color: c.line, thickness: 1, space: 1),
    appBarTheme: AppBarTheme(
      backgroundColor: c.panel, foregroundColor: c.ink, elevation: 0, scrolledUnderElevation: 0.5, centerTitle: false,
      titleTextStyle: TextStyle(color: c.ink, fontSize: 20, fontWeight: FontWeight.w700, letterSpacing: -0.3),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: c.panel, indicatorColor: c.greenSoft, elevation: 0, height: 66,
      labelTextStyle: WidgetStateProperty.resolveWith((s) => TextStyle(fontSize: 12, fontWeight: s.contains(WidgetState.selected) ? FontWeight.w700 : FontWeight.w500, color: s.contains(WidgetState.selected) ? c.ink : c.muted)),
      iconTheme: WidgetStateProperty.resolveWith((s) => IconThemeData(color: s.contains(WidgetState.selected) ? c.greenInk : c.muted, size: 24)),
    ),
    // Primary action: the brand green, one per screen.
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: c.green, foregroundColor: c.brandInk, disabledBackgroundColor: c.soft, disabledForegroundColor: c.muted,
        minimumSize: const Size(0, 48), shape: RoundedRectangleBorder(borderRadius: radius), textStyle: const TextStyle(fontSize: 15.5, fontWeight: FontWeight.w700),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: c.ink, side: BorderSide(color: c.line), minimumSize: const Size(0, 44),
        shape: RoundedRectangleBorder(borderRadius: radius), textStyle: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w600),
      ),
    ),
    textButtonTheme: TextButtonThemeData(style: TextButton.styleFrom(foregroundColor: c.greenInk, textStyle: const TextStyle(fontWeight: FontWeight.w600))),
    floatingActionButtonTheme: FloatingActionButtonThemeData(backgroundColor: c.green, foregroundColor: c.brandInk, elevation: 2),
    inputDecorationTheme: InputDecorationTheme(
      filled: true, fillColor: c.panel, contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      border: OutlineInputBorder(borderRadius: radius, borderSide: BorderSide(color: c.line)),
      enabledBorder: OutlineInputBorder(borderRadius: radius, borderSide: BorderSide(color: c.line)),
      focusedBorder: OutlineInputBorder(borderRadius: radius, borderSide: BorderSide(color: c.greenInk, width: 1.6)),
      hintStyle: TextStyle(color: c.muted), labelStyle: TextStyle(color: c.ink2),
    ),
    chipTheme: ChipThemeData(
      backgroundColor: c.panel, selectedColor: c.greenSoft, side: BorderSide(color: c.line), showCheckmark: false,
      labelStyle: TextStyle(color: c.ink, fontWeight: FontWeight.w600, fontSize: 13.5), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999)),
    ),
    tabBarTheme: TabBarThemeData(
      labelColor: c.ink, unselectedLabelColor: c.muted, indicatorColor: c.greenInk, dividerColor: c.line,
      labelStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14.5), unselectedLabelStyle: const TextStyle(fontWeight: FontWeight.w500, fontSize: 14.5),
    ),
    snackBarTheme: SnackBarThemeData(behavior: SnackBarBehavior.floating, backgroundColor: c.ink, contentTextStyle: TextStyle(color: c.panel, fontWeight: FontWeight.w500), shape: RoundedRectangleBorder(borderRadius: radius)),
    dialogTheme: DialogThemeData(backgroundColor: c.panel, surfaceTintColor: Colors.transparent, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18))),
    bottomSheetTheme: BottomSheetThemeData(backgroundColor: c.panel, surfaceTintColor: Colors.transparent, showDragHandle: true),
    listTileTheme: ListTileThemeData(iconColor: c.ink2, textColor: c.ink),
    progressIndicatorTheme: ProgressIndicatorThemeData(color: c.greenInk),
  );
}

Color hexColor(String? hex, Color fallback) {
  if (hex == null) return fallback;
  final v = int.tryParse(hex.replaceFirst('#', ''), radix: 16);
  return v == null ? fallback : Color(0xFF000000 | v);
}

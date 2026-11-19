import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../theme.dart';
import '../widgets.dart';

/// The opening screen. The phone's own launch screen is plain brand green; this starts from that same
/// green, then brings in the logo, the name, the line and a loading bar.
/// It looks the same in the light and dark themes, like a brand's front door.
class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  static const green = Color(0xFF14301C);
  static const tile = 112.0;

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(vsync: this, duration: const Duration(milliseconds: 1500));

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (MediaQuery.disableAnimationsOf(context)) {
      _controller.value = 1;
    } else if (_controller.status == AnimationStatus.dismissed) {
      _controller.forward();
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  /// 0→1 over one slice of the timeline, eased.
  double _part(double from, double to) => Curves.easeOutCubic.transform(((_controller.value - from) / (to - from)).clamp(0.0, 1.0));

  @override
  Widget build(BuildContext context) {
    const mint = Color(0xFFB0EC9C);
    const onGreen = Color(0xFFF2F7F1);
    final size = MediaQuery.sizeOf(context);
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        backgroundColor: SplashScreen.green,
        body: AnimatedBuilder(
          animation: _controller,
          builder: (context, _) {
            final logo = _part(0, 0.4);
            final glow = _part(0.1, 0.6);
            final name = _part(0.3, 0.75);
            final line = _part(0.45, 0.9);
            final bar = _part(0.6, 1);
            return Stack(fit: StackFit.expand, children: [
              // Depth: lighter in the middle, darker at the edges.
              Opacity(
                opacity: glow,
                child: const DecoratedBox(
                  decoration: BoxDecoration(gradient: RadialGradient(center: Alignment(0, -0.05), radius: 1.1, colors: [Color(0xFF1F4A2D), SplashScreen.green, Color(0xFF0C1E12)], stops: [0, 0.55, 1])),
                ),
              ),
              Center(
                child: Container(
                  width: SplashScreen.tile + 150 * glow, height: SplashScreen.tile + 150 * glow,
                  decoration: BoxDecoration(shape: BoxShape.circle, gradient: RadialGradient(colors: [mint.withValues(alpha: 0.30 * glow), mint.withValues(alpha: 0)])),
                ),
              ),
              Center(child: Opacity(opacity: logo, child: Transform.scale(scale: 0.82 + 0.18 * logo, child: const LogoTile(size: SplashScreen.tile, glow: false)))),
              Positioned(
                left: 24, right: 24, top: size.height / 2 + SplashScreen.tile / 2 + 26,
                child: Column(children: [
                  Opacity(
                    opacity: name,
                    child: Transform.translate(
                      offset: Offset(0, 12 * (1 - name)),
                      child: const Text('atwebtech', style: TextStyle(fontFamily: displayFont, fontWeight: FontWeight.w600, fontSize: 34, letterSpacing: -1.2, height: 1.1, color: onGreen)),
                    ),
                  ),
                  const SizedBox(height: 8),
                  Opacity(
                    opacity: line,
                    child: Transform.translate(
                      offset: Offset(0, 10 * (1 - line)),
                      child: Text('Plan, schedule and publish. All in one place.', textAlign: TextAlign.center, style: TextStyle(fontFamily: bodyFont, fontSize: 15, color: onGreen.withValues(alpha: 0.72))),
                    ),
                  ),
                ]),
              ),
              Positioned(
                left: 0, right: 0, bottom: MediaQuery.paddingOf(context).bottom + 44,
                child: Opacity(
                  opacity: bar,
                  child: Column(children: [
                    SizedBox(
                      width: 132,
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(4),
                        child: LinearProgressIndicator(minHeight: 3, color: mint, backgroundColor: Colors.white.withValues(alpha: 0.12)),
                      ),
                    ),
                    const SizedBox(height: 14),
                    Text('atwebtech.us', style: TextStyle(fontFamily: bodyFont, fontSize: 12.5, letterSpacing: 0.3, color: onGreen.withValues(alpha: 0.5))),
                  ]),
                ),
              ),
            ]);
          },
        ),
      ),
    );
  }
}

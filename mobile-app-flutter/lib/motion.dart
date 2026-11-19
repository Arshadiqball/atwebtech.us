import 'dart:math' as math;

import 'package:flutter/material.dart';

import 'theme.dart';

/// Marks the moment a screen's content arrived. [Appear] widgets created right after it animate in;
/// ones created later (rows scrolled into view) show at once, so scrolling never replays the entrance.
class AppearScope extends StatefulWidget {
  const AppearScope({super.key, required this.child});
  final Widget child;

  @override
  State<AppearScope> createState() => _AppearScopeState();
}

class _AppearScopeState extends State<AppearScope> {
  final _born = DateTime.now();

  @override
  Widget build(BuildContext context) => _AppearBorn(born: _born, child: widget.child);
}

class _AppearBorn extends InheritedWidget {
  const _AppearBorn({required this.born, required super.child});
  final DateTime born;

  @override
  bool updateShouldNotify(_AppearBorn old) => false;
}

/// Fades and slides its child in. [index] staggers a list: each row starts a little after the one above.
class Appear extends StatefulWidget {
  const Appear({super.key, required this.child, this.index = 0, this.rise = 16, this.scale = false});
  final Widget child;
  final int index;
  final double rise;
  final bool scale;

  @override
  State<Appear> createState() => _AppearState();
}

class _AppearState extends State<Appear> with SingleTickerProviderStateMixin {
  AnimationController? _controller;
  Animation<double>? _curve;
  bool _decided = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_decided) return;
    _decided = true;
    final born = context.getInheritedWidgetOfExactType<_AppearBorn>()?.born;
    final late = born != null && DateTime.now().difference(born) > const Duration(milliseconds: 700);
    if (late || MediaQuery.disableAnimationsOf(context)) return;
    // The wait is part of the animation itself (no timers to cancel).
    final wait = math.min(widget.index, 9) * 55;
    const run = 440;
    final controller = AnimationController(vsync: this, duration: Duration(milliseconds: wait + run));
    _controller = controller;
    _curve = CurvedAnimation(parent: controller, curve: Interval(wait / (wait + run), 1, curve: Curves.easeOutCubic));
    controller.forward();
  }

  @override
  void dispose() {
    _controller?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final curve = _curve;
    if (curve == null) return widget.child;
    return AnimatedBuilder(
      animation: curve,
      child: widget.child,
      builder: (context, child) {
        final t = curve.value;
        if (t >= 1) return child!;
        Widget out = Transform.translate(offset: Offset(0, (1 - t) * widget.rise), child: child);
        if (widget.scale) out = Transform.scale(scale: 0.92 + 0.08 * t, child: out);
        return Opacity(opacity: t, child: out);
      },
    );
  }
}

/// Wraps each widget of a column or list in a staggered [Appear].
List<Widget> staggered(List<Widget> children) => [for (var i = 0; i < children.length; i++) Appear(index: i, child: children[i])];

/// A number that counts up to its value (and on to the next one when it changes). Other text shows as is.
class CountUp extends StatelessWidget {
  const CountUp(this.value, {super.key, this.style});
  final String value;
  final TextStyle? style;

  static String _group(int n) => n.toString().replaceAllMapped(RegExp(r'(\d)(?=(\d{3})+$)'), (m) => '${m[1]},');

  @override
  Widget build(BuildContext context) {
    final grouped = value.contains(',');
    final n = int.tryParse(value.replaceAll(',', ''));
    if (n == null || n < 0 || MediaQuery.disableAnimationsOf(context)) return Text(value, style: style);
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: n.toDouble()),
      duration: const Duration(milliseconds: 900),
      curve: Curves.easeOutCubic,
      builder: (_, v, _) => Text(grouped ? _group(v.round()) : '${v.round()}', style: style),
    );
  }
}

/// A chart bar that grows to its height.
class GrowBar extends StatelessWidget {
  const GrowBar({super.key, required this.height, required this.color, this.index = 0});
  final double height;
  final Color color;
  final int index;

  @override
  Widget build(BuildContext context) {
    final decoration = BoxDecoration(color: color, borderRadius: const BorderRadius.vertical(top: Radius.circular(3)));
    if (MediaQuery.disableAnimationsOf(context)) return Container(height: height, decoration: decoration);
    final wait = math.min(index, 40) * 14;
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: Duration(milliseconds: wait + 520),
      curve: Interval(wait / (wait + 520), 1, curve: Curves.easeOutCubic),
      builder: (_, t, _) => Container(height: 3 + (height - 3).clamp(0, double.infinity) * t, decoration: decoration),
    );
  }
}

/// Placeholder cards with a moving sheen, shown while a list loads.
class Skeleton extends StatefulWidget {
  const Skeleton({super.key, this.rows = 4});
  final int rows;

  @override
  State<Skeleton> createState() => _SkeletonState();
}

class _SkeletonState extends State<Skeleton> with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(vsync: this, duration: const Duration(milliseconds: 1300));

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (MediaQuery.disableAnimationsOf(context)) {
      _controller.stop();
    } else if (!_controller.isAnimating) {
      _controller.repeat();
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    Widget bar(double width, double height) => Container(width: width, height: height, decoration: BoxDecoration(color: c.soft, borderRadius: BorderRadius.circular(6)));
    final rows = Column(children: [
      for (var i = 0; i < widget.rows; i++)
        Container(
          margin: const EdgeInsets.only(bottom: 10),
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(color: c.panel, borderRadius: BorderRadius.circular(14), border: Border.all(color: c.line)),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Container(width: 34, height: 34, decoration: BoxDecoration(color: c.soft, shape: BoxShape.circle)),
              const SizedBox(width: 10),
              Column(crossAxisAlignment: CrossAxisAlignment.start, children: [bar(120, 11), const SizedBox(height: 6), bar(80, 9)]),
            ]),
            const SizedBox(height: 14),
            bar(double.infinity, 10),
            const SizedBox(height: 8),
            bar(i.isEven ? 220 : 160, 10),
          ]),
        ),
    ]);
    return Semantics(
      label: 'Loading',
      child: SingleChildScrollView(
        physics: const NeverScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
        child: AnimatedBuilder(
          animation: _controller,
          child: rows,
          builder: (context, child) => ShaderMask(
            blendMode: BlendMode.srcATop,
            shaderCallback: (rect) {
              final x = -1.5 + 3 * _controller.value;
              return LinearGradient(
                begin: Alignment(x - 0.6, -0.3), end: Alignment(x + 0.6, 0.3),
                colors: [Colors.transparent, c.panel.withValues(alpha: 0.55), Colors.transparent],
              ).createShader(rect);
            },
            child: child,
          ),
        ),
      ),
    );
  }
}

/// The shell's tabs: each one is built the first time it is opened (so its entrance plays then),
/// stays alive afterwards, and fades up when you switch to it.
class LazyTabs extends StatefulWidget {
  const LazyTabs({super.key, required this.index, required this.builders});
  final int index;
  final List<WidgetBuilder> builders;

  @override
  State<LazyTabs> createState() => _LazyTabsState();
}

class _LazyTabsState extends State<LazyTabs> with SingleTickerProviderStateMixin {
  late final Set<int> _opened = {widget.index};
  late final AnimationController _controller = AnimationController(vsync: this, duration: const Duration(milliseconds: 260), value: 1);
  late final Animation<double> _curve = CurvedAnimation(parent: _controller, curve: Curves.easeOutCubic);

  @override
  void didUpdateWidget(LazyTabs old) {
    super.didUpdateWidget(old);
    if (old.index != widget.index) {
      _opened.add(widget.index);
      if (!MediaQuery.disableAnimationsOf(context)) _controller.forward(from: 0);
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _curve,
      builder: (context, child) => Opacity(opacity: _curve.value, child: Transform.translate(offset: Offset(0, (1 - _curve.value) * 10), child: child)),
      child: IndexedStack(
        index: widget.index,
        children: [for (var i = 0; i < widget.builders.length; i++) _opened.contains(i) ? AppearScope(child: Builder(builder: widget.builders[i])) : const SizedBox.shrink()],
      ),
    );
  }
}

/// Soft drifting glows behind the sign-in and loading screens.
class Aurora extends StatefulWidget {
  const Aurora({super.key, required this.child});
  final Widget child;

  @override
  State<Aurora> createState() => _AuroraState();
}

class _AuroraState extends State<Aurora> with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(vsync: this, duration: const Duration(seconds: 14));

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (MediaQuery.disableAnimationsOf(context)) {
      _controller.stop();
    } else if (!_controller.isAnimating) {
      _controller.repeat();
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    final dark = Theme.of(context).brightness == Brightness.dark;
    Widget glow(Color color, double size) => IgnorePointer(
          child: Container(width: size, height: size, decoration: BoxDecoration(shape: BoxShape.circle, gradient: RadialGradient(colors: [color, color.withValues(alpha: 0)]))),
        );
    return Stack(fit: StackFit.expand, children: [
      ColoredBox(color: c.bg),
      AnimatedBuilder(
        animation: _controller,
        builder: (context, _) {
          final a = _controller.value * 2 * math.pi;
          return Stack(fit: StackFit.expand, children: [
            Align(alignment: Alignment(-0.9 + 0.25 * math.sin(a), -0.95 + 0.12 * math.cos(a)), child: glow(c.green.withValues(alpha: dark ? 0.16 : 0.42), 380)),
            Align(alignment: Alignment(1.1 + 0.2 * math.cos(a), 0.9 + 0.1 * math.sin(a)), child: glow((dark ? c.infoInk : c.infoBg).withValues(alpha: dark ? 0.10 : 0.9), 420)),
          ]);
        },
      ),
      widget.child,
    ]);
  }
}

/// A brief check mark over the screen after something important went through (a post scheduled, a reply sent).
void celebrate(BuildContext context, String label, {IconData icon = Icons.check_rounded}) {
  final overlay = Navigator.of(context, rootNavigator: true).overlay;
  if (overlay == null || MediaQuery.disableAnimationsOf(context)) return;
  late final OverlayEntry entry;
  entry = OverlayEntry(builder: (_) => _Celebration(label: label, icon: icon, onDone: () => entry.remove()));
  overlay.insert(entry);
}

class _Celebration extends StatefulWidget {
  const _Celebration({required this.label, required this.icon, required this.onDone});
  final String label;
  final IconData icon;
  final VoidCallback onDone;

  @override
  State<_Celebration> createState() => _CelebrationState();
}

class _CelebrationState extends State<_Celebration> with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(vsync: this, duration: const Duration(milliseconds: 1250));

  @override
  void initState() {
    super.initState();
    _controller.forward().whenComplete(widget.onDone);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    return IgnorePointer(
      child: AnimatedBuilder(
        animation: _controller,
        builder: (context, _) {
          final t = _controller.value;
          final pop = Curves.elasticOut.transform((t / 0.55).clamp(0, 1));
          final fade = t < 0.75 ? 1.0 : 1 - (t - 0.75) / 0.25;
          final ring = Curves.easeOut.transform((t / 0.7).clamp(0, 1));
          return Opacity(
            opacity: fade.clamp(0, 1),
            child: Center(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                SizedBox(
                  width: 150, height: 150,
                  child: Stack(alignment: Alignment.center, children: [
                    Container(width: 84 + 66 * ring, height: 84 + 66 * ring, decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: c.green.withValues(alpha: (1 - ring) * 0.9), width: 3))),
                    Transform.scale(
                      scale: pop,
                      child: Container(
                        width: 84, height: 84,
                        decoration: BoxDecoration(color: c.green, shape: BoxShape.circle, boxShadow: [BoxShadow(color: c.green.withValues(alpha: 0.45), blurRadius: 28, spreadRadius: 2)]),
                        child: Icon(widget.icon, color: c.brandInk, size: 46),
                      ),
                    ),
                  ]),
                ),
                Material(
                  color: c.ink, borderRadius: BorderRadius.circular(999),
                  child: Padding(padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8), child: Text(widget.label, style: TextStyle(color: c.panel, fontWeight: FontWeight.w700))),
                ),
              ]),
            ),
          );
        },
      ),
    );
  }
}

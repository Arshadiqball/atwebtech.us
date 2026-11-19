import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'api.dart';
import 'fx.dart';
import 'motion.dart';
import 'state.dart';
import 'theme.dart';

/// A card on the page canvas.
class AppCard extends StatefulWidget {
  const AppCard({super.key, required this.child, this.padding = const EdgeInsets.all(16), this.accent, this.onTap});
  final Widget child;
  final EdgeInsets padding;
  final Color? accent;
  final VoidCallback? onTap;

  @override
  State<AppCard> createState() => _AppCardState();
}

class _AppCardState extends State<AppCard> {
  bool _pressed = false;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    final dark = Theme.of(context).brightness == Brightness.dark;
    final child = widget.child;
    final padding = widget.padding;
    final accent = widget.accent;
    final onTap = widget.onTap;
    // A Material (not a colored Container) so list rows and taps inside the card can show their ink.
    // The colored edge needs the row to be as tall as the content; plain cards skip that extra layout pass.
    final content = accent == null
        ? Padding(padding: padding, child: child)
        : IntrinsicHeight(
            child: Row(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Container(width: 4, color: accent),
              Expanded(child: Padding(padding: padding, child: child)),
            ]),
          );
    final card = DecoratedBox(
      // A soft lift in light mode. In dark mode the lighter panel on the darker canvas does that job.
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(14),
        boxShadow: dark ? null : [BoxShadow(color: c.ink.withValues(alpha: 0.05), blurRadius: 14, offset: const Offset(0, 4))],
      ),
      child: Material(
        color: c.panel,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14), side: BorderSide(color: c.line)),
        clipBehavior: Clip.antiAlias,
        child: onTap == null
            ? content
            : InkWell(
                onTap: () {
                  Fx.tap();
                  onTap();
                },
                onHighlightChanged: (down) => setState(() => _pressed = down),
                child: content,
              ),
      ),
    );
    // Cards you can tap give a little under the finger.
    return onTap == null ? card : AnimatedScale(scale: _pressed ? 0.975 : 1, duration: const Duration(milliseconds: 120), curve: Curves.easeOut, child: card);
  }
}

/// The atwebtech logo as the website panel shows it: the mark with the wordmark beside it.
class Brand extends StatelessWidget {
  const Brand({super.key, this.size = 28, this.color});
  final double size;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: 'atwebtech',
      excludeSemantics: true,
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        // On dark backgrounds the mark's dark half is drawn light, so the whole letter stays readable.
        Image.asset(Theme.of(context).brightness == Brightness.dark ? 'assets/logo-dark.png' : 'assets/logo.png', width: size, height: size, filterQuality: FilterQuality.medium),
        SizedBox(width: size * 0.27),
        Text('atwebtech', style: TextStyle(fontFamily: displayFont, fontWeight: FontWeight.w600, fontSize: size * 0.77, letterSpacing: -size * 0.027, height: 1, color: color ?? context.c.ink)),
      ]),
    );
  }
}

/// The atwebtech mark on a light tile, so its dark half stays visible on the dark theme.
class LogoTile extends StatelessWidget {
  const LogoTile({super.key, this.size = 76, this.glow = true});
  final double size;
  final bool glow;

  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    return Container(
      width: size, height: size,
      padding: EdgeInsets.all(size * 0.16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(size * 0.28),
        boxShadow: glow ? [BoxShadow(color: context.c.green.withValues(alpha: dark ? 0.22 : 0.5), blurRadius: 28, offset: const Offset(0, 8))] : null,
      ),
      child: Image.asset('assets/logo.png'),
    );
  }
}

/// The channel's initial with its network color.
class ChannelAvatar extends StatelessWidget {
  const ChannelAvatar({super.key, required this.network, this.name, this.size = 36});
  final String network;
  final String? name;
  final double size;

  @override
  Widget build(BuildContext context) {
    final app = context.read<AppState>();
    final c = context.c;
    final meta = app.network(network);
    final color = hexColor(meta['color'] as String?, c.muted);
    final label = (name ?? meta['name'] ?? '?').toString().replaceAll('@', '').trim();
    return Stack(clipBehavior: Clip.none, children: [
      CircleAvatar(radius: size / 2, backgroundColor: c.soft, child: Text(label.isEmpty ? '?' : label[0].toUpperCase(), style: TextStyle(color: c.ink, fontWeight: FontWeight.w700, fontSize: size * 0.4))),
      Positioned(right: -2, bottom: -2, child: Container(width: size * 0.42, height: size * 0.42, decoration: BoxDecoration(color: color, shape: BoxShape.circle, border: Border.all(color: c.panel, width: 2)))),
    ]);
  }
}

enum Tone { neutral, ok, warn, bad, info }

class Pill extends StatelessWidget {
  const Pill(this.text, {super.key, this.tone = Tone.neutral, this.icon});
  final String text;
  final Tone tone;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    final (bg, fg) = switch (tone) {
      Tone.ok => (c.greenSoft, c.greenInk),
      Tone.warn => (c.warnBg, c.warnInk),
      Tone.bad => (c.badBg, c.badInk),
      Tone.info => (c.infoBg, c.infoInk),
      Tone.neutral => (c.soft, c.ink2),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 3),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(999)),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        if (icon != null) ...[Icon(icon, size: 13, color: fg), const SizedBox(width: 4)],
        Text(text, style: TextStyle(color: fg, fontSize: 12, fontWeight: FontWeight.w700)),
      ]),
    );
  }
}

/// A colored callout, used for errors and notes inside cards.
class Callout extends StatelessWidget {
  const Callout(this.text, {super.key, this.tone = Tone.bad, this.icon = Icons.error_outline});
  final String text;
  final Tone tone;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    final (bg, fg) = switch (tone) {
      Tone.warn => (c.warnBg, c.warnInk),
      Tone.info => (c.infoBg, c.infoInk),
      Tone.ok => (c.greenSoft, c.greenInk),
      _ => (c.badBg, c.badInk),
    };
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(9)),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(icon, size: 16, color: fg),
        const SizedBox(width: 7),
        Expanded(child: Text(text, style: TextStyle(color: fg, fontSize: 13, height: 1.35))),
      ]),
    );
  }
}

class EmptyView extends StatelessWidget {
  const EmptyView({super.key, required this.icon, required this.title, this.text, this.action, this.onAction});
  final IconData icon;
  final String title;
  final String? text;
  final String? action;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Appear(scale: true, child: Container(width: 64, height: 64, decoration: BoxDecoration(color: c.greenSoft, borderRadius: BorderRadius.circular(18)), child: Icon(icon, color: c.greenInk, size: 30))),
          const SizedBox(height: 16),
          Appear(index: 1, child: Text(title, textAlign: TextAlign.center, style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: c.ink))),
          if (text != null) ...[const SizedBox(height: 6), Appear(index: 2, child: Text(text!, textAlign: TextAlign.center, style: TextStyle(color: c.muted, height: 1.4)))],
          if (action != null) ...[const SizedBox(height: 18), Appear(index: 3, child: FilledButton(onPressed: onAction, child: Text(action!)))],
        ]),
      ),
    );
  }
}

class ErrorView extends StatelessWidget {
  const ErrorView({super.key, required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => EmptyView(icon: Icons.cloud_off_outlined, title: 'Couldn’t load this', text: message, action: 'Try again', onAction: onRetry);
}

/// Loads data once, shows a spinner, an error with retry, or the content with pull-to-refresh.
class Loaded<T> extends StatefulWidget {
  const Loaded({super.key, required this.load, required this.builder});
  final Future<T> Function() load;
  final Widget Function(BuildContext context, T data, Future<void> Function() reload) builder;

  @override
  State<Loaded<T>> createState() => LoadedState<T>();
}

class LoadedState<T> extends State<Loaded<T>> {
  T? _data;
  String? _error;
  bool _loading = true;
  int _arrivals = 0;

  @override
  void initState() {
    super.initState();
    reload();
  }

  Future<void> reload() async {
    try {
      final data = await widget.load();
      if (!mounted) return;
      setState(() {
        if (_data == null) _arrivals++; // the first content plays its entrance
        _data = data;
        _error = null;
        _loading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() { _error = e.message; _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    final Widget child;
    if (_loading && _data == null) {
      child = const Skeleton(key: ValueKey('loading'));
    } else if (_error != null && _data == null) {
      child = ErrorView(key: const ValueKey('error'), message: _error!, onRetry: () { setState(() => _loading = true); reload(); });
    } else {
      child = AppearScope(
        key: ValueKey(_arrivals),
        child: RefreshIndicator(
          onRefresh: () async {
            await reload();
            Fx.pop();
          },
          child: widget.builder(context, _data as T, reload),
        ),
      );
    }
    return AnimatedSwitcher(
      duration: const Duration(milliseconds: 260),
      switchInCurve: Curves.easeOut,
      layoutBuilder: (current, previous) => Stack(alignment: Alignment.topCenter, fit: StackFit.expand, children: [...previous, ?current]),
      child: child,
    );
  }
}

/// Shows what happened at the bottom of the screen, with a sound. [error] marks something that did not work.
void showSnack(BuildContext context, String? message, {bool error = false, bool quiet = false}) {
  if (message == null || message.isEmpty || !context.mounted) return;
  final c = context.c;
  if (!quiet) error ? Fx.error() : Fx.success();
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(
      duration: Duration(milliseconds: error ? 4500 : 2800),
      content: Row(children: [
        Icon(error ? Icons.error_outline : Icons.check_circle_outline, color: error ? c.bad : c.green, size: 20),
        const SizedBox(width: 10),
        Expanded(child: Text(message)),
      ]),
    ));
}

/// Runs a server action, shows what the server said, and returns true when it went through.
Future<bool> act(BuildContext context, Future<Map<String, dynamic>> Function() action, {String? done}) async {
  try {
    final res = await action();
    if (context.mounted) showSnack(context, done ?? res['message']?.toString());
    return true;
  } on ApiException catch (e) {
    if (context.mounted) showSnack(context, e.detail, error: true);
    return false;
  }
}

Future<bool> confirm(BuildContext context, String title, String text, {String yes = 'Delete', bool danger = true}) async {
  final c = context.c;
  final ok = await showDialog<bool>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: Text(title),
      content: Text(text),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
        TextButton(onPressed: () => Navigator.pop(ctx, true), style: danger ? TextButton.styleFrom(foregroundColor: c.bad) : null, child: Text(yes)),
      ],
    ),
  );
  return ok == true;
}

/// A small tinted button for the main action of a row that repeats down a list.
class TintButton extends StatelessWidget {
  const TintButton({super.key, required this.label, required this.onPressed, this.icon});
  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    final style = FilledButton.styleFrom(backgroundColor: c.greenSoft, foregroundColor: c.greenInk, minimumSize: const Size(0, 38), padding: const EdgeInsets.symmetric(horizontal: 14), textStyle: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w700));
    return icon == null
        ? FilledButton(onPressed: onPressed, style: style, child: Text(label))
        : FilledButton.icon(onPressed: onPressed, style: style, icon: Icon(icon, size: 16), label: Text(label));
  }
}

class SmallButton extends StatelessWidget {
  const SmallButton({super.key, required this.label, required this.onPressed, this.icon, this.danger = false});
  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    final style = OutlinedButton.styleFrom(minimumSize: const Size(0, 38), padding: const EdgeInsets.symmetric(horizontal: 12), foregroundColor: danger ? c.bad : c.ink, textStyle: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w600));
    return icon == null
        ? OutlinedButton(onPressed: onPressed, style: style, child: Text(label))
        : OutlinedButton.icon(onPressed: onPressed, style: style, icon: Icon(icon, size: 16), label: Text(label));
  }
}

class SectionTitle extends StatelessWidget {
  const SectionTitle(this.text, {super.key, this.trailing});
  final String text;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(4, 18, 4, 8),
        child: Row(children: [
          Expanded(child: Text(text.toUpperCase(), style: TextStyle(color: context.c.muted, fontSize: 11.5, fontWeight: FontWeight.w700, letterSpacing: 0.8))),
          ?trailing,
        ]),
      );
}

/// Switches between the light and dark theme, like the sun and moon button on the website.
class ThemeToggle extends StatelessWidget {
  const ThemeToggle({super.key});

  @override
  Widget build(BuildContext context) {
    final app = context.read<AppState>();
    final dark = Theme.of(context).brightness == Brightness.dark;
    return IconButton(
      tooltip: dark ? 'Switch to light theme' : 'Switch to dark theme',
      onPressed: () {
        Fx.toggle();
        app.setTheme(dark ? ThemeMode.light : ThemeMode.dark);
      },
      icon: AnimatedSwitcher(
        duration: const Duration(milliseconds: 320),
        transitionBuilder: (child, animation) => RotationTransition(turns: Tween(begin: 0.6, end: 1.0).animate(animation), child: ScaleTransition(scale: animation, child: FadeTransition(opacity: animation, child: child))),
        child: Icon(dark ? Icons.light_mode_outlined : Icons.dark_mode_outlined, key: ValueKey(dark)),
      ),
    );
  }
}

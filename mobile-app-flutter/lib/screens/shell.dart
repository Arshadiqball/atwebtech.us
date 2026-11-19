import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../fx.dart';
import '../motion.dart';
import '../state.dart';
import '../theme.dart';
import '../widgets.dart';
import 'composer.dart';
import 'home.dart';
import 'inbox.dart';
import 'insights.dart';
import 'more.dart';
import 'notifications.dart';
import 'publish.dart';

/// Lets any screen jump to a tab (Home's to-do cards, notifications, push taps).
class ShellNav extends ChangeNotifier {
  int tab = 0;
  String publishTab = 'queue';
  int publishRev = 0;
  int dataRev = 0;

  /// A post changed (approved, retried, deleted…): every Publish tab quietly reloads, since a post
  /// leaving one tab usually lands in another.
  void postsChanged() {
    dataRev++;
    notifyListeners();
  }

  /// Back to Home, for the next person who signs in on this phone.
  void reset() {
    tab = 0;
    publishTab = 'queue';
  }

  void go(int index) {
    tab = index;
    notifyListeners();
  }

  /// Reloads the Publish lists (after a post was saved).
  void reloadPublish() {
    publishRev++;
    notifyListeners();
  }

  void openPublish(String name) {
    tab = 1;
    publishTab = name;
    publishRev++;
    notifyListeners();
  }
}

class Shell extends StatelessWidget {
  const Shell({super.key});

  static Future<void> compose(BuildContext context, {int? postId, Map<String, dynamic>? prefill}) async {
    final app = context.read<AppState>();
    final nav = context.read<ShellNav>();
    // A message still on screen would sit on top of the composer's Schedule button.
    ScaffoldMessenger.of(context).hideCurrentSnackBar();
    final saved = await Navigator.of(context).push<String>(MaterialPageRoute(fullscreenDialog: true, builder: (_) => ComposerScreen(postId: postId, prefill: prefill)));
    if (saved != null) {
      await app.refresh();
      // Show the post where it landed.
      nav.openPublish(switch (saved) { 'draft' => 'drafts', 'review' => 'approvals', _ => 'queue' });
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final nav = context.watch<ShellNav>();
    final c = context.c;
    final unread = app.count('notifications');
    final inbox = app.count('inbox');
    const titles = ['Home', 'Publish', 'Community', 'Insights', 'More'];
    return Scaffold(
      appBar: AppBar(
        title: AnimatedSwitcher(
          duration: const Duration(milliseconds: 220),
          layoutBuilder: (current, previous) => Stack(alignment: Alignment.centerLeft, children: [...previous, ?current]),
          transitionBuilder: (child, animation) => FadeTransition(opacity: animation, child: SlideTransition(position: Tween(begin: const Offset(0, 0.35), end: Offset.zero).animate(animation), child: child)),
          child: nav.tab == 0 ? const Brand(key: ValueKey(0)) : Text(titles[nav.tab], key: ValueKey(nav.tab)),
        ),
        actions: [
          const ThemeToggle(),
          IconButton(
            tooltip: 'Notifications',
            onPressed: () {
              Fx.tap();
              Navigator.of(context).push(MaterialPageRoute(builder: (_) => const NotificationsScreen()));
            },
            icon: Badge(isLabelVisible: unread > 0, label: Text(unread > 99 ? '99+' : '$unread'), backgroundColor: c.bad, child: _Bell(ringing: unread > 0)),
          ),
          const SizedBox(width: 4),
        ],
        bottom: PreferredSize(preferredSize: const Size.fromHeight(1), child: Divider(height: 1, color: c.line)),
      ),
      // Keyed by workspace so every tab reloads when the person switches workspace.
      body: KeyedSubtree(
        key: ValueKey(app.workspace['id']),
        child: PageWidth(child: LazyTabs(index: nav.tab, builders: [
          (_) => const HomeScreen(),
          (_) => const PublishScreen(),
          (_) => const InboxScreen(),
          (_) => const InsightsScreen(),
          (_) => const MoreScreen(),
        ])),
      ),
      floatingActionButton: nav.tab == 1
          ? FloatingActionButton.extended(onPressed: () { Fx.tap(); Shell.compose(context); }, icon: const Icon(Icons.add), label: const Text('New post', style: TextStyle(fontWeight: FontWeight.w700)))
          : null,
      bottomNavigationBar: Container(
        decoration: BoxDecoration(border: Border(top: BorderSide(color: c.line))),
        child: NavigationBar(
          selectedIndex: nav.tab,
          onDestinationSelected: (i) {
            if (i != nav.tab) Fx.tap();
            nav.go(i);
          },
          destinations: [
            const NavigationDestination(icon: Icon(Icons.home_outlined), selectedIcon: Icon(Icons.home_rounded), label: 'Home'),
            const NavigationDestination(icon: Icon(Icons.calendar_today_outlined), selectedIcon: Icon(Icons.calendar_today_rounded), label: 'Publish'),
            NavigationDestination(icon: Badge(isLabelVisible: inbox > 0, label: Text('$inbox'), backgroundColor: c.bad, child: const Icon(Icons.forum_outlined)), selectedIcon: const Icon(Icons.forum_rounded), label: 'Community'),
            const NavigationDestination(icon: Icon(Icons.bar_chart_outlined), selectedIcon: Icon(Icons.bar_chart_rounded), label: 'Insights'),
            const NavigationDestination(icon: Icon(Icons.menu_rounded), label: 'More'),
          ],
        ),
      ),
    );
  }
}

/// The notification bell. It gives a short ring when something unread arrives.
class _Bell extends StatefulWidget {
  const _Bell({required this.ringing});
  final bool ringing;

  @override
  State<_Bell> createState() => _BellState();
}

class _BellState extends State<_Bell> with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(vsync: this, duration: const Duration(milliseconds: 900));

  @override
  void initState() {
    super.initState();
    if (widget.ringing) _controller.forward();
  }

  @override
  void didUpdateWidget(_Bell old) {
    super.didUpdateWidget(old);
    if (widget.ringing && !old.ringing && !MediaQuery.disableAnimationsOf(context)) _controller.forward(from: 0);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _controller,
      child: Icon(widget.ringing ? Icons.notifications_active_outlined : Icons.notifications_none_rounded),
      // A swing that fades out: left, right, left, rest.
      builder: (context, child) {
        final t = _controller.value;
        final angle = t >= 1 ? 0.0 : 0.32 * (1 - t) * _wave(t * 3);
        return Transform.rotate(angle: angle, alignment: Alignment.topCenter, child: child);
      },
    );
  }

  static double _wave(double turns) {
    final x = turns % 1;
    return x < 0.25 ? x * 4 : (x < 0.75 ? 2 - x * 4 : x * 4 - 4);
  }
}

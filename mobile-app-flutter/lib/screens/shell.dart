import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../state.dart';
import '../theme.dart';
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
    final saved = await Navigator.of(context).push<bool>(MaterialPageRoute(fullscreenDialog: true, builder: (_) => ComposerScreen(postId: postId, prefill: prefill)));
    if (saved == true) {
      await app.refresh();
      nav.reloadPublish();
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
        title: Text(titles[nav.tab]),
        actions: [
          IconButton(
            tooltip: 'Notifications',
            onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const NotificationsScreen())),
            icon: Badge(isLabelVisible: unread > 0, label: Text(unread > 99 ? '99+' : '$unread'), backgroundColor: c.bad, child: const Icon(Icons.notifications_none_rounded)),
          ),
          const SizedBox(width: 4),
        ],
        bottom: PreferredSize(preferredSize: const Size.fromHeight(1), child: Divider(height: 1, color: c.line)),
      ),
      // Keyed by workspace so every tab reloads when the person switches workspace.
      body: KeyedSubtree(
        key: ValueKey(app.workspace['id']),
        child: IndexedStack(index: nav.tab, children: const [HomeScreen(), PublishScreen(), InboxScreen(), InsightsScreen(), MoreScreen()]),
      ),
      floatingActionButton: nav.tab <= 1
          ? FloatingActionButton.extended(onPressed: () => Shell.compose(context), icon: const Icon(Icons.add), label: const Text('New post', style: TextStyle(fontWeight: FontWeight.w700)))
          : null,
      bottomNavigationBar: Container(
        decoration: BoxDecoration(border: Border(top: BorderSide(color: c.line))),
        child: NavigationBar(
          selectedIndex: nav.tab,
          onDestinationSelected: nav.go,
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

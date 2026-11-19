import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../motion.dart';
import '../state.dart';
import '../theme.dart';
import '../widgets.dart';
import 'more.dart';
import 'shell.dart';

/// Everything that needed the person: failed posts, approvals, channels to reconnect, weekly summary.
class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  final _loader = GlobalKey<LoadedState<List<Map<String, dynamic>>>>();

  Future<List<Map<String, dynamic>>> _load() async {
    final res = await context.read<AppState>().api.get('/api/mobile/notifications');
    return (res['items'] as List).map((e) => Map<String, dynamic>.from(e as Map)).toList();
  }

  Future<void> _open(Map<String, dynamic> n) async {
    final app = context.read<AppState>();
    final nav = context.read<ShellNav>();
    final navigator = Navigator.of(context);
    try {
      await app.api.postJson('/api/mobile/notifications/${n['id']}/read', {});
    } catch (_) {}
    // Go to the workspace it happened in, then to the right screen.
    final ws = (n['workspace_id'] as num?)?.toInt();
    if (ws != null && ws != 0 && ws != (app.workspace['id'] as num?)?.toInt()) {
      await app.switchWorkspace(ws);
    } else {
      await app.refresh();
    }
    final url = (n['url'] ?? '').toString();
    if (url.contains('tab=failed')) {
      navigator.pop();
      nav.openPublish('failed');
    } else if (url.contains('tab=approvals')) {
      navigator.pop();
      nav.openPublish('approvals');
    } else if (url.contains('tab=drafts')) {
      navigator.pop();
      nav.openPublish('drafts');
    } else if (url.startsWith('/app/publish')) {
      navigator.pop();
      nav.openPublish('queue');
    } else if (url.startsWith('/app/insights')) {
      navigator.pop();
      nav.go(3);
    } else if (url.startsWith('/app/community')) {
      navigator.pop();
      nav.go(2);
    } else if (url.startsWith('/app/channels')) {
      navigator.pushReplacement(MaterialPageRoute(builder: (_) => const ChannelsScreen()));
    } else {
      _loader.currentState?.reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = context.read<AppState>();
    final c = context.c;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: [
          TextButton(
            onPressed: () async {
              if (await act(context, () => app.api.postForm('/app/notifications', {}))) {
                await _loader.currentState?.reload();
                await app.refresh();
              }
            },
            child: const Text('Mark all read'),
          ),
        ],
      ),
      body: Loaded<List<Map<String, dynamic>>>(
        key: _loader,
        load: _load,
        builder: (context, items, reload) {
          if (items.isEmpty) {
            return ListView(children: const [SizedBox(height: 460, child: EmptyView(icon: Icons.notifications_none_rounded, title: 'Nothing yet', text: 'You’ll hear here when a post fails, a channel needs reconnecting, or a post is waiting for approval.'))]);
          }
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: items.length,
            separatorBuilder: (_, _) => const SizedBox(height: 10),
            itemBuilder: (context, i) {
              final n = items[i];
              final unread = n['unread'] == true;
              final icon = switch (n['kind']) {
                'post_failed' => Icons.error_outline,
                'channel_reconnect' => Icons.link_off,
                'approval_needed' => Icons.pending_actions_outlined,
                'approval_decided' => Icons.verified_outlined,
                'queue_low' => Icons.calendar_today_outlined,
                'weekly_digest' => Icons.insights_outlined,
                'announcement' => Icons.campaign_outlined,
                _ => Icons.notifications_none_rounded,
              };
              return Appear(
                key: ValueKey('n-${n['id']}'),
                index: i,
                child: AppCard(
                accent: unread ? c.greenInk : null,
                padding: const EdgeInsets.all(14),
                onTap: () => _open(n),
                child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Icon(icon, color: unread ? c.greenInk : c.muted, size: 22),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(n['title'].toString(), style: TextStyle(fontWeight: unread ? FontWeight.w700 : FontWeight.w600, color: c.ink)),
                      if ((n['body'] ?? '').toString().isNotEmpty) Padding(padding: const EdgeInsets.only(top: 3), child: Text(n['body'].toString(), style: TextStyle(color: c.ink2, fontSize: 13.5, height: 1.35))),
                      const SizedBox(height: 4),
                      Text([n['when'], if (n['workspace'] != null) n['workspace'], if (unread) 'new'].join(' · '), style: TextStyle(color: c.muted, fontSize: 12)),
                    ]),
                  ),
                ]),
              ));
            },
          );
        },
      ),
    );
  }
}

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../state.dart';
import '../theme.dart';
import '../widgets.dart';
import 'more.dart';
import 'shell.dart';

/// Opens with what needs the person, then quick actions and this week's numbers.
class HomeScreen extends StatelessWidget {
  const HomeScreen({super.key});

  void _go(BuildContext context, String where) {
    final nav = context.read<ShellNav>();
    switch (where) {
      case 'failed' || 'approvals' || 'drafts':
        nav.openPublish(where);
      case 'inbox':
        nav.go(2);
      case 'compose':
        Shell.compose(context);
      case 'channels':
        Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ChannelsScreen()));
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final c = context.c;
    final hour = app.workspaceNow.hour;
    final greeting = hour < 12 ? 'Good morning' : (hour < 18 ? 'Good afternoon' : 'Good evening');
    final name = (app.me['name'] ?? '').toString();
    final todo = app.todo;
    final hasChannels = app.channels.isNotEmpty;
    return RefreshIndicator(
      onRefresh: app.refresh,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 18, 16, 96),
        children: [
          Text('$greeting${name.isEmpty ? '' : ', $name'}', style: TextStyle(fontSize: 23, fontWeight: FontWeight.w800, letterSpacing: -0.5, color: c.ink)),
          const SizedBox(height: 2),
          Text('${app.workspace['name'] ?? 'My workspace'} · ${app.workspace['plan'] ?? 'Free'} plan', style: TextStyle(color: c.muted)),
          if (app.loadError != null) ...[const SizedBox(height: 14), Callout(app.loadError!, tone: Tone.warn, icon: Icons.cloud_off_outlined)],
          const SizedBox(height: 16),
          if (!hasChannels)
            AppCard(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('Connect your first channel', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700, color: c.ink)),
                const SizedBox(height: 4),
                Text('Link Instagram, Facebook, LinkedIn, YouTube, Pinterest, Threads, Bluesky or Mastodon to start publishing.', style: TextStyle(color: c.ink2, height: 1.4)),
                const SizedBox(height: 14),
                FilledButton.icon(onPressed: () => _go(context, 'channels'), icon: const Icon(Icons.add), label: const Text('Connect a channel')),
              ]),
            )
          else if (todo.isEmpty)
            const Callout('You’re all caught up. Nothing needs you right now.', tone: Tone.ok, icon: Icons.check_circle_outline)
          else
            ...todo.map((t) {
              final (tone, icon) = switch (t['style']) {
                'bad' => (Tone.bad, Icons.error_outline),
                'warn' => (Tone.warn, Icons.pending_actions_outlined),
                'info' => (Tone.info, Icons.forum_outlined),
                _ => (Tone.ok, Icons.edit_outlined),
              };
              final (bg, fg) = switch (tone) {
                Tone.bad => (c.badBg, c.bad),
                Tone.warn => (c.warnBg, c.warnInk),
                Tone.info => (c.infoBg, c.infoInk),
                _ => (c.greenSoft, c.greenInk),
              };
              return Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: AppCard(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  onTap: () => _go(context, (t['go'] ?? '').toString()),
                  child: Row(children: [
                    Container(width: 40, height: 40, decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(11)), child: Icon(icon, color: fg, size: 21)),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text((t['title'] ?? '').toString(), style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15, color: c.ink)),
                        Text((t['detail'] ?? '').toString(), style: TextStyle(color: c.muted, fontSize: 13)),
                      ]),
                    ),
                    Icon(Icons.chevron_right_rounded, color: c.muted),
                  ]),
                ),
              );
            }),
          const SectionTitle('This week'),
          Row(children: [
            Expanded(child: _Stat('${app.count('queue')}', 'Scheduled')),
            const SizedBox(width: 10),
            Expanded(child: _Stat('${app.count('sent_week')}', 'Published')),
            const SizedBox(width: 10),
            Expanded(child: _Stat('${app.channels.length}', 'Channels')),
          ]),
          const SectionTitle('Quick actions'),
          Wrap(spacing: 8, runSpacing: 8, children: [
            SmallButton(label: 'Automate my week', icon: Icons.bolt_outlined, onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const AutomationsScreen()))),
            SmallButton(label: 'See what’s working', icon: Icons.bar_chart_outlined, onPressed: () => context.read<ShellNav>().go(3)),
            SmallButton(label: 'Media library', icon: Icons.photo_library_outlined, onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const LibraryScreen()))),
            SmallButton(label: 'Channels', icon: Icons.hub_outlined, onPressed: () => _go(context, 'channels')),
          ]),
        ],
      ),
    );
  }
}

class _Stat extends StatelessWidget {
  const _Stat(this.value, this.label);
  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    return AppCard(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(value, style: TextStyle(fontSize: 24, fontWeight: FontWeight.w800, color: c.ink)),
        Text(label, style: TextStyle(color: c.muted, fontSize: 12.5)),
      ]),
    );
  }
}

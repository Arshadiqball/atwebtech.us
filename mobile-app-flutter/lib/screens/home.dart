import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../fx.dart';
import '../motion.dart';
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
        padding: const EdgeInsets.fromLTRB(16, 18, 16, 28),
        children: staggered([
          _Hero(
            greeting: '$greeting${name.isEmpty ? '' : ', $name'}',
            sub: '${app.workspace['name'] ?? 'My workspace'} · ${app.workspace['plan'] ?? 'Free'} plan',
            stats: [('${app.count('queue')}', 'Scheduled'), ('${app.count('sent_week')}', 'Published'), ('${app.channels.length}', 'Channels')],
            onCompose: hasChannels ? () { Fx.tap(); Shell.compose(context); } : null,
          ),
          if (app.loadError != null) Padding(padding: const EdgeInsets.only(top: 14), child: Callout(app.loadError!, tone: Tone.warn, icon: Icons.cloud_off_outlined)),
          SectionTitle(hasChannels && todo.isNotEmpty ? 'Needs you' : 'Today'),
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
          const SectionTitle('Quick actions'),
          Row(children: [
            Expanded(child: _Action(icon: Icons.bolt_outlined, label: 'Automate my week', bg: c.warnBg, fg: c.warnInk, onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const AutomationsScreen())))),
            const SizedBox(width: 10),
            Expanded(child: _Action(icon: Icons.bar_chart_outlined, label: 'See what’s working', bg: c.infoBg, fg: c.infoInk, onTap: () => context.read<ShellNav>().go(3))),
          ]),
          Padding(
            padding: const EdgeInsets.only(top: 10),
            child: Row(children: [
              Expanded(child: _Action(icon: Icons.photo_library_outlined, label: 'Media library', bg: c.greenSoft, fg: c.greenInk, onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const LibraryScreen())))),
              const SizedBox(width: 10),
              Expanded(child: _Action(icon: Icons.hub_outlined, label: 'Channels', bg: c.soft, fg: c.ink2, onTap: () => _go(context, 'channels'))),
            ]),
          ),
        ]),
      ),
    );
  }
}

/// The greeting on a brand-green panel, with this week's numbers counting up.
class _Hero extends StatelessWidget {
  const _Hero({required this.greeting, required this.sub, required this.stats, this.onCompose});
  final VoidCallback? onCompose;
  final String greeting;
  final String sub;
  final List<(String, String)> stats;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    final dark = Theme.of(context).brightness == Brightness.dark;
    const onHero = Color(0xFFF2F7F1);
    return Container(
      padding: const EdgeInsets.fromLTRB(18, 18, 18, 16),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        gradient: LinearGradient(
          begin: Alignment.topLeft, end: Alignment.bottomRight,
          colors: dark ? const [Color(0xFF1D3A28), Color(0xFF12211A), Color(0xFF151C1B)] : const [Color(0xFF1B4428), Color(0xFF14301C), Color(0xFF0F2416)],
        ),
        border: Border.all(color: dark ? c.green.withValues(alpha: 0.22) : Colors.transparent),
        boxShadow: [BoxShadow(color: (dark ? Colors.black : const Color(0xFF14301C)).withValues(alpha: dark ? 0.5 : 0.28), blurRadius: 24, offset: const Offset(0, 10))],
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(greeting, style: const TextStyle(fontFamily: displayFont, fontSize: 22, fontWeight: FontWeight.w600, letterSpacing: -0.3, color: onHero)),
        const SizedBox(height: 3),
        Text(sub, style: TextStyle(color: onHero.withValues(alpha: 0.72))),
        const SizedBox(height: 16),
        Row(children: [
          for (var i = 0; i < stats.length; i++) ...[
            if (i > 0) const SizedBox(width: 8),
            Expanded(
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
                decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.09), borderRadius: BorderRadius.circular(13), border: Border.all(color: Colors.white.withValues(alpha: 0.1))),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  CountUp(stats[i].$1, style: TextStyle(fontSize: 24, fontWeight: FontWeight.w800, color: c.green, height: 1.1)),
                  const SizedBox(height: 2),
                  Text(stats[i].$2, style: TextStyle(color: onHero.withValues(alpha: 0.78), fontSize: 12.5)),
                ]),
              ),
            ),
          ],
        ]),
        // The one main action of this screen.
        if (onCompose != null) ...[
          const SizedBox(height: 12),
          SizedBox(width: double.infinity, child: FilledButton.icon(onPressed: onCompose, style: FilledButton.styleFrom(backgroundColor: c.green, foregroundColor: const Color(0xFF14301C)), icon: const Icon(Icons.add), label: const Text('New post'))),
        ],
      ]),
    );
  }
}

/// A shortcut tile on Home.
class _Action extends StatelessWidget {
  const _Action({required this.icon, required this.label, required this.bg, required this.fg, required this.onTap});
  final IconData icon;
  final String label;
  final Color bg;
  final Color fg;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return AppCard(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
      onTap: onTap,
      child: Row(children: [
        Container(width: 36, height: 36, decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(10)), child: Icon(icon, color: fg, size: 20)),
        const SizedBox(width: 10),
        Expanded(child: Text(label, maxLines: 2, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5, height: 1.2, color: context.c.ink))),
      ]),
    );
  }
}

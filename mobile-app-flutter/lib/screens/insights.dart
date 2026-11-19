import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../state.dart';
import '../theme.dart';
import '../widgets.dart';

/// Stats for the workspace: totals with change, posts per day, best times, hashtags, top posts.
class InsightsScreen extends StatefulWidget {
  const InsightsScreen({super.key});

  @override
  State<InsightsScreen> createState() => _InsightsScreenState();
}

class _InsightsScreenState extends State<InsightsScreen> {
  String _range = '30d';
  final _loader = GlobalKey<LoadedState<Map<String, dynamic>>>();
  static const _ranges = {'7d': '7 days', '30d': '30 days', 'mtd': 'This month', 'last-month': 'Last month'};

  Future<Map<String, dynamic>> _load() => context.read<AppState>().api.get('/api/mobile/insights', {'range': _range});

  List<Map<String, dynamic>> _maps(dynamic v) => (v as List? ?? []).map((e) => Map<String, dynamic>.from(e as Map)).toList();

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final c = context.c;
    return Loaded<Map<String, dynamic>>(
      key: _loader,
      load: _load,
      builder: (context, d, reload) {
        final cards = _maps(d['cards']);
        final series = _maps(d['series']);
        final best = Map<String, dynamic>.from(d['best'] as Map);
        final tags = _maps(d['hashtags']);
        final top = _maps(d['top_posts']);
        final issues = _maps(d['issues']);
        final maxN = series.fold<int>(1, (m, s) => (s['n'] as int) > m ? s['n'] as int : m);
        return ListView(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
          children: [
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(children: [
                for (final e in _ranges.entries)
                  Padding(padding: const EdgeInsets.only(right: 8), child: ChoiceChip(label: Text(e.value), selected: _range == e.key, onSelected: (_) { setState(() => _range = e.key); _loader.currentState?.reload(); })),
              ]),
            ),
            const SizedBox(height: 6),
            Text(d['period'].toString(), style: TextStyle(color: c.muted, fontSize: 12.5)),
            for (final i in issues) Padding(padding: const EdgeInsets.only(top: 10), child: Callout('${i['channel']}: ${i['message']}', tone: Tone.warn, icon: Icons.link_off)),
            const SizedBox(height: 12),
            GridView.count(
              crossAxisCount: 2, shrinkWrap: true, physics: const NeverScrollableScrollPhysics(), crossAxisSpacing: 10, mainAxisSpacing: 10, childAspectRatio: 1.9,
              children: [
                for (final card in cards)
                  AppCard(
                    padding: const EdgeInsets.all(14),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
                      Text(card['label'].toString(), style: TextStyle(color: c.muted, fontSize: 12.5)),
                      const SizedBox(height: 2),
                      Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
                        Text(card['value'].toString(), style: TextStyle(fontSize: 23, fontWeight: FontWeight.w800, color: c.ink)),
                        const SizedBox(width: 6),
                        if ((card['delta'] ?? '').toString().isNotEmpty)
                          Padding(padding: const EdgeInsets.only(bottom: 4), child: Text(card['delta'].toString(), style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: card['delta'].toString().startsWith('-') ? c.bad : c.greenInk))),
                      ]),
                    ]),
                  ),
              ],
            ),
            const SectionTitle('Posts published per day'),
            AppCard(
              child: SizedBox(
                height: 110,
                child: Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
                  for (final s in series)
                    Expanded(
                      child: Tooltip(
                        message: '${s['day']}: ${s['n']}',
                        child: Container(margin: const EdgeInsets.symmetric(horizontal: 1), height: 4 + 100 * (s['n'] as int) / maxN, decoration: BoxDecoration(color: (s['n'] as int) > 0 ? c.greenInk : c.line, borderRadius: const BorderRadius.vertical(top: Radius.circular(3)))),
                      ),
                    ),
                ]),
              ),
            ),
            const SectionTitle('Best times to post'),
            if (best['ready'] == true)
              Wrap(spacing: 8, runSpacing: 8, children: [
                for (final t in _maps(best['top']))
                  AppCard(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
                      Text(t['label'].toString(), style: TextStyle(fontWeight: FontWeight.w700, color: c.ink)),
                      Text('${t['lift']} your usual · ${t['posts']} posts', style: TextStyle(color: c.muted, fontSize: 12)),
                    ]),
                  ),
              ])
            else
              Callout('atwebtech needs stats from more posts sent at the same hours to find your best times. It has ${best['posts']} so far.', tone: Tone.info, icon: Icons.schedule),
            if (tags.isNotEmpty) ...[
              const SectionTitle('Hashtag performance'),
              AppCard(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                child: Column(children: [
                  for (final t in tags)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 7),
                      child: Row(children: [
                        Expanded(child: Text(t['tag'].toString(), style: TextStyle(fontWeight: FontWeight.w700, color: c.ink))),
                        Text('${t['posts']} posts', style: TextStyle(color: c.muted, fontSize: 12.5)),
                        const SizedBox(width: 12),
                        Pill(t['lift'].toString(), tone: t['up'] == true ? Tone.ok : Tone.neutral),
                      ]),
                    ),
                ]),
              ),
            ],
            if (top.isNotEmpty) ...[
              const SectionTitle('Top posts'),
              for (final p in top)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: AppCard(
                    padding: const EdgeInsets.all(14),
                    onTap: p['url'] == null ? null : () => app.api.openLink(p['url'] as String),
                    child: Row(children: [
                      ChannelAvatar(network: p['slug'] as String, size: 30),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text(p['text'].toString(), maxLines: 2, overflow: TextOverflow.ellipsis, style: TextStyle(color: c.ink, height: 1.35)),
                          const SizedBox(height: 3),
                          Text('${p['engagement']} engagements${p['views'] != null ? ' · ${p['views']} views' : ''}', style: TextStyle(color: c.muted, fontSize: 12.5)),
                        ]),
                      ),
                    ]),
                  ),
                ),
            ],
            const SizedBox(height: 8),
            OutlinedButton.icon(onPressed: () => act(context, () async { await app.api.openWeb('/app/insights'); return {}; }), icon: const Icon(Icons.open_in_new, size: 17), label: const Text('Full insights and reports')),
          ],
        );
      },
    );
  }
}

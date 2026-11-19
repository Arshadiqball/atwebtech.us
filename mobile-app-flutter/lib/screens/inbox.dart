import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api.dart';
import '../fx.dart';
import '../motion.dart';
import '../state.dart';
import '../theme.dart';
import '../widgets.dart';
import 'more.dart';

/// Comments from connected channels: reply, use a saved reply, get an AI suggestion, or mark done.
class InboxScreen extends StatefulWidget {
  const InboxScreen({super.key});

  @override
  State<InboxScreen> createState() => _InboxScreenState();
}

class _InboxScreenState extends State<InboxScreen> {
  String _state = 'open';
  final _loader = GlobalKey<LoadedState<Map<String, dynamic>>>();
  static const _labels = {'open': 'Unanswered', 'replied': 'Answered', 'done': 'Done', 'all': 'All'};

  Future<Map<String, dynamic>> _load() => context.read<AppState>().api.get('/api/mobile/inbox', {'state': _state});

  Future<void> _reload() async {
    await _loader.currentState?.reload();
    if (mounted) await context.read<AppState>().refresh();
  }

  Future<void> _do(Map<String, dynamic> fields) async {
    final api = context.read<AppState>().api;
    if (await act(context, () => api.postForm('/app/community', fields))) await _reload();
  }

  Future<void> _reply(Map<String, dynamic> comment, List<Map<String, dynamic>> saved, bool ai) async {
    final sent = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _ReplySheet(comment: comment, saved: saved, ai: ai),
    );
    if (sent == true) await _reload();
  }

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final c = context.c;
    return Loaded<Map<String, dynamic>>(
      key: _loader,
      load: _load,
      builder: (context, data, reload) {
        final comments = (data['comments'] as List).map((e) => Map<String, dynamic>.from(e as Map)).toList();
        final saved = (data['saved'] as List).map((e) => Map<String, dynamic>.from(e as Map)).toList();
        final counts = Map<String, dynamic>.from(data['counts'] as Map);
        final problems = (data['problems'] as List).map((e) => e.toString()).toList();
        if (data['has_channels'] != true) {
          return ListView(children: [
            SizedBox(height: 460, child: EmptyView(icon: Icons.forum_outlined, title: 'Answer every comment from one inbox', text: 'Connect YouTube, a Facebook Page, Instagram, Threads, Bluesky or Mastodon. New comments arrive every 30 minutes.', action: 'Connect a channel', onAction: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ChannelsScreen())))),
          ]);
        }
        return ListView(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
          children: [
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(children: [
                for (final e in _labels.entries)
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      label: Text('${e.value} ${counts[e.key] ?? 0}'),
                      selected: _state == e.key,
                      onSelected: (_) { Fx.tap(); setState(() => _state = e.key); _loader.currentState?.reload(); },
                    ),
                  ),
                ActionChip(avatar: const Icon(Icons.refresh, size: 16), label: const Text('Check now'), onPressed: () => _do({'action': 'refresh'})),
              ]),
            ),
            for (final p in problems) Padding(padding: const EdgeInsets.only(top: 10), child: Callout(p, tone: Tone.warn, icon: Icons.link_off)),
            const SizedBox(height: 12),
            if (comments.isEmpty)
              SizedBox(height: 320, child: EmptyView(icon: Icons.check_circle_outline, title: _state == 'open' ? 'You’re all caught up' : 'Nothing here', text: _state == 'open' ? 'Nothing is waiting for an answer.' : null)),
            for (final (i, cm) in comments.indexed)
              Appear(
                key: ValueKey('comment-${cm['id']}-$_state'),
                index: i,
                child: Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: AppCard(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Row(children: [
                      ChannelAvatar(network: cm['network'] as String, name: cm['author'] as String?, size: 34),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text(cm['author'].toString(), style: TextStyle(fontWeight: FontWeight.w700, color: c.ink)),
                          Text('${app.network(cm['network'] as String)['name']} · ${cm['channel']} · ${cm['when']}', maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: c.muted, fontSize: 12.5)),
                        ]),
                      ),
                      if (cm['url'] != null) IconButton(tooltip: 'Open on the network', icon: Icon(Icons.open_in_new, size: 19, color: c.muted), onPressed: () => app.api.openLink(cm['url'] as String)),
                    ]),
                    const SizedBox(height: 10),
                    Text(cm['text'].toString(), style: TextStyle(color: c.ink, fontSize: 14.5, height: 1.45)),
                    if (cm['on'] != null) ...[const SizedBox(height: 6), Text('On: ${cm['on']}', maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: c.muted, fontSize: 12.5))],
                    if (cm['reply'] != null) ...[
                      const SizedBox(height: 10),
                      Callout('${cm['auto_reply'] == true ? 'Auto-reply' : 'You replied'}: ${cm['reply']}', tone: Tone.ok, icon: Icons.reply),
                    ],
                    const SizedBox(height: 12),
                    Wrap(spacing: 8, runSpacing: 8, children: [
                      if (cm['state'] != 'done' && cm['can_reply'] == true && app.canSchedule) TintButton(label: 'Reply', icon: Icons.reply, onPressed: () => _reply(cm, saved, data['ai'] == true)),
                      if (cm['state'] != 'done' && cm['can_reply'] != true) Text('Replying needs comment access for this network. Reply there, then mark it done.', style: TextStyle(color: c.muted, fontSize: 12.5)),
                      if (cm['state'] != 'done' && app.canSchedule) SmallButton(label: 'Mark done', icon: Icons.check, onPressed: () => _do({'action': 'done', 'id': cm['id']})),
                      if (cm['state'] == 'done' && app.canSchedule) SmallButton(label: 'Move to Unanswered', onPressed: () => _do({'action': 'reopen', 'id': cm['id']})),
                    ]),
                  ]),
                ),
              )),
            if (data['more'] == true) Center(child: Text('Showing the latest 40. Answer some to see older ones.', style: TextStyle(color: c.muted, fontSize: 12.5))),
          ],
        );
      },
    );
  }
}

class _ReplySheet extends StatefulWidget {
  const _ReplySheet({required this.comment, required this.saved, required this.ai});
  final Map<String, dynamic> comment;
  final List<Map<String, dynamic>> saved;
  final bool ai;

  @override
  State<_ReplySheet> createState() => _ReplySheetState();
}

class _ReplySheetState extends State<_ReplySheet> {
  final _text = TextEditingController();
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _text.dispose();
    super.dispose();
  }

  Future<void> _suggest() async {
    final api = context.read<AppState>().api;
    setState(() { _busy = true; _error = null; });
    try {
      final res = await api.postForm('/app/community', {'action': 'suggest', 'id': widget.comment['id']});
      final draft = res['draft'];
      if (draft is Map && mounted) {
        setState(() => _text.text = (draft['text'] ?? '').toString());
      } else if (mounted) {
        setState(() => _error = res['message']?.toString());
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _send() async {
    final api = context.read<AppState>().api;
    if (_text.text.trim().isEmpty) return;
    setState(() { _busy = true; _error = null; });
    try {
      final res = await api.postForm('/app/community', {'action': 'reply', 'id': widget.comment['id'], 'reply': _text.text.trim()});
      final message = (res['message'] ?? '').toString();
      if (!mounted) return;
      // The website reports a refused reply as a notice, keeping the text in the box.
      if (res['draft'] is Map || !message.startsWith('Reply sent')) {
        Fx.error();
        setState(() => _error = message);
      } else {
        Fx.send();
        celebrate(context, 'Reply sent', icon: Icons.reply_rounded);
        showSnack(context, message, quiet: true);
        Navigator.of(context).pop(true);
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 0, 16, MediaQuery.of(context).viewInsets.bottom + 16),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Text('Reply to ${widget.comment['author']}', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700, color: c.ink)),
        const SizedBox(height: 4),
        Text(widget.comment['text'].toString(), maxLines: 3, overflow: TextOverflow.ellipsis, style: TextStyle(color: c.muted)),
        const SizedBox(height: 12),
        if (_error != null) ...[Callout(_error!), const SizedBox(height: 10)],
        TextField(controller: _text, autofocus: true, minLines: 3, maxLines: 6, maxLength: 2000, textCapitalization: TextCapitalization.sentences, decoration: const InputDecoration(hintText: 'Write a reply…')),
        Wrap(spacing: 8, runSpacing: 4, children: [
          if (widget.ai) ActionChip(avatar: const Icon(Icons.auto_awesome, size: 16), label: const Text('Suggest a reply'), onPressed: _busy ? null : _suggest),
          for (final s in widget.saved) ActionChip(label: Text(s['title'].toString()), onPressed: () => setState(() => _text.text = s['body'].toString())),
        ]),
        const SizedBox(height: 12),
        FilledButton(onPressed: _busy ? null : _send, child: Text(_busy ? 'Working…' : 'Send reply')),
      ]),
    );
  }
}

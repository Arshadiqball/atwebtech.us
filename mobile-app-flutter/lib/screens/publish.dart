import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../state.dart';
import '../theme.dart';
import '../widgets.dart';
import 'shell.dart';

/// Queue, Drafts, Approvals, Failed and Sent, with the same actions as the website.
class PublishScreen extends StatefulWidget {
  const PublishScreen({super.key});

  @override
  State<PublishScreen> createState() => _PublishScreenState();
}

class _PublishScreenState extends State<PublishScreen> with SingleTickerProviderStateMixin {
  static const _tabs = ['queue', 'drafts', 'approvals', 'failed', 'sent'];
  static const _labels = ['Queue', 'Drafts', 'Approvals', 'Failed', 'Sent'];
  static const _countKeys = ['queue', 'drafts', 'review', 'failed', 'sent'];
  late final TabController _controller = TabController(length: _tabs.length, vsync: this);
  int _rev = -1;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final nav = context.watch<ShellNav>();
    if (nav.publishRev != _rev) {
      _rev = nav.publishRev;
      final index = _tabs.indexOf(nav.publishTab);
      if (index >= 0 && index != _controller.index) _controller.index = index;
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final nav = context.watch<ShellNav>();
    final c = context.c;
    return Column(children: [
      Material(
        color: c.panel,
        child: TabBar(
          controller: _controller,
          isScrollable: true,
          tabAlignment: TabAlignment.start,
          tabs: [
            for (var i = 0; i < _tabs.length; i++)
              Tab(
                child: Row(mainAxisSize: MainAxisSize.min, children: [
                  Text(_labels[i]),
                  const SizedBox(width: 6),
                  Pill('${app.count(_countKeys[i])}', tone: _tabs[i] == 'failed' && app.count('failed') > 0 ? Tone.bad : (_tabs[i] == 'approvals' && app.count('review') > 0 ? Tone.warn : Tone.neutral)),
                ]),
              ),
          ],
        ),
      ),
      Expanded(
        child: TabBarView(
          controller: _controller,
          children: [for (final tab in _tabs) _PostList(key: ValueKey('$tab-${nav.publishRev}-${app.workspace['id']}'), tab: tab)],
        ),
      ),
    ]);
  }
}

class _PostList extends StatefulWidget {
  const _PostList({super.key, required this.tab});
  final String tab;

  @override
  State<_PostList> createState() => _PostListState();
}

class _PostListState extends State<_PostList> with AutomaticKeepAliveClientMixin {
  final _loader = GlobalKey<LoadedState<List<Map<String, dynamic>>>>();

  @override
  bool get wantKeepAlive => true;

  Future<List<Map<String, dynamic>>> _load() async {
    final res = await context.read<AppState>().api.get('/api/mobile/posts', {'tab': widget.tab});
    return (res['posts'] as List).map((e) => Map<String, dynamic>.from(e as Map)).toList();
  }

  Future<void> _after(bool ok) async {
    if (!ok || !mounted) return;
    await context.read<AppState>().refresh();
    await _loader.currentState?.reload();
  }

  Future<void> _post(Map<String, dynamic> fields, {String path = '/app/posts'}) async {
    final api = context.read<AppState>().api;
    await _after(await act(context, () => api.postForm(path, fields)));
  }

  Future<void> _delete(Map<String, dynamic> p) async {
    if (!await confirm(context, 'Delete this post?', 'This can’t be undone.')) return;
    await _post({'action': 'delete', 'id': p['id']});
  }

  Future<void> _requestChanges(Map<String, dynamic> p) async {
    final note = TextEditingController();
    final sent = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Ask for changes'),
        content: TextField(controller: note, maxLines: 3, autofocus: true, decoration: const InputDecoration(hintText: 'What should change?')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Send back')),
        ],
      ),
    );
    if (sent == true) await _post({'action': 'changes', 'id': p['id'], 'note': note.text.trim()}, path: '/app/approvals');
  }

  Future<void> _duplicate(Map<String, dynamic> p) async {
    final api = context.read<AppState>().api;
    try {
      final res = await api.postForm('/app/posts', {'action': 'duplicate', 'id': p['id']});
      final id = int.tryParse(RegExp(r'id=(\d+)').firstMatch((res['location'] ?? '').toString())?.group(1) ?? '');
      if (!mounted) return;
      await context.read<AppState>().refresh();
      if (mounted && id != null) await Shell.compose(context, postId: id);
    } catch (e) {
      if (mounted) showSnack(context, e.toString());
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final app = context.watch<AppState>();
    final c = context.c;
    final tab = widget.tab;
    return Loaded<List<Map<String, dynamic>>>(
      key: _loader,
      load: _load,
      builder: (context, posts, reload) {
        if (posts.isEmpty) {
          final (icon, title, text) = switch (tab) {
            'drafts' => (Icons.edit_note_outlined, 'No drafts', 'Save a post as a draft to finish it later.'),
            'approvals' => (Icons.verified_outlined, 'Nothing waiting for approval', 'Posts sent for approval show up here.'),
            'failed' => (Icons.check_circle_outline, 'No failed posts', 'Everything published as planned.'),
            'sent' => (Icons.send_outlined, 'Nothing sent yet', 'Posts atwebtech publishes for you appear here.'),
            _ => (Icons.calendar_today_outlined, 'Your queue is empty', 'Schedule a post and it will go out on time.'),
          };
          return ListView(children: [SizedBox(height: 420, child: EmptyView(icon: icon, title: title, text: text, action: tab == 'queue' || tab == 'drafts' ? 'New post' : null, onAction: () => Shell.compose(context)))]);
        }
        return ListView.separated(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 96),
          itemCount: posts.length + (tab == 'failed' ? 1 : 0),
          separatorBuilder: (_, _) => const SizedBox(height: 10),
          itemBuilder: (context, index) {
            if (tab == 'failed' && index == 0) {
              return Row(children: [
                Expanded(child: Text('Fix what each message asks for, then retry.', style: TextStyle(color: c.muted, fontSize: 13))),
                if (app.canSchedule) ...[
                  SmallButton(label: 'Delete all', danger: true, onPressed: () async {
                    if (await confirm(context, 'Delete all failed posts?', 'This removes ${posts.length} posts and can’t be undone.')) {
                      await _post({'action': 'delete_all', 'status': 'failed', 'ids': posts.map((p) => p['id']).toList()});
                    }
                  }),
                  const SizedBox(width: 8),
                  FilledButton(style: FilledButton.styleFrom(minimumSize: const Size(0, 38)), onPressed: () => _post({'action': 'retry_all'}), child: const Text('Retry all')),
                ],
              ]);
            }
            final p = posts[index - (tab == 'failed' ? 1 : 0)];
            return _PostCard(
              post: p,
              tab: tab,
              actions: [
                if (tab == 'failed' && app.canSchedule) TintButton(label: 'Retry', icon: Icons.refresh, onPressed: () => _post({'action': 'retry', 'id': p['id']})),
                if (tab == 'approvals' && app.canSchedule) ...[
                  TintButton(label: 'Approve', icon: Icons.check, onPressed: () => _post({'action': 'approve', 'id': p['id']}, path: '/app/approvals')),
                  SmallButton(label: 'Ask for changes', onPressed: () => _requestChanges(p)),
                ],
                if (tab != 'sent') SmallButton(label: 'Edit', icon: Icons.edit_outlined, onPressed: () => Shell.compose(context, postId: p['id'] as int)),
                if (tab == 'sent' && p['url'] != null) SmallButton(label: 'View', icon: Icons.open_in_new, onPressed: () => app.api.openLink(p['url'] as String)),
                if (tab == 'sent' || tab == 'drafts') SmallButton(label: 'Duplicate', icon: Icons.copy_outlined, onPressed: () => _duplicate(p)),
                if (tab == 'sent' && app.canSchedule)
                  SmallButton(
                    label: p['evergreen'] == true ? 'Evergreen' : 'Recycle', icon: Icons.autorenew,
                    onPressed: () => _post({'id': p['id'], 'on': p['evergreen'] == true ? '' : '1'}, path: '/app/evergreen'),
                  ),
                if (tab != 'sent' && (app.canSchedule || tab == 'drafts')) IconButton(tooltip: 'Delete', onPressed: () => _delete(p), icon: Icon(Icons.delete_outline, color: c.muted)),
              ],
            );
          },
        );
      },
    );
  }
}

class _PostCard extends StatelessWidget {
  const _PostCard({required this.post, required this.tab, required this.actions});
  final Map<String, dynamic> post;
  final String tab;
  final List<Widget> actions;

  @override
  Widget build(BuildContext context) {
    final app = context.read<AppState>();
    final c = context.c;
    final network = (post['network'] ?? '').toString();
    final title = (post['title'] ?? '').toString();
    final text = (post['text'] ?? '').toString();
    final media = post['media'] as Map?;
    final photos = (post['photos'] as num?)?.toInt() ?? 0;
    final stats = post['stats'] as Map?;
    final when = post['when']?.toString();
    final meta = [
      if (when != null) (tab == 'sent' ? 'Published $when' : (tab == 'failed' ? 'Was due $when' : when)),
      if (tab == 'failed') 'tried ${post['attempts']} ${post['attempts'] == 1 ? 'time' : 'times'}',
    ].join(' · ');
    return AppCard(
      accent: tab == 'failed' ? c.bad : null,
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          ChannelAvatar(network: network, name: post['channel']?.toString(), size: 32),
          const SizedBox(width: 10),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text((post['channel'] ?? app.network(network)['name']).toString(), maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontWeight: FontWeight.w700, color: c.ink)),
              if (meta.isNotEmpty) Text(meta, style: TextStyle(color: c.muted, fontSize: 12.5)),
            ]),
          ),
          if (post['repeat'] != null) Padding(padding: const EdgeInsets.only(left: 6), child: Pill('Repeats', tone: Tone.ok, icon: Icons.repeat)),
          if (tab == 'queue') const Padding(padding: EdgeInsets.only(left: 6), child: Pill('Scheduled', tone: Tone.ok)),
        ]),
        const SizedBox(height: 10),
        Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              if (title.isNotEmpty) Text(title, style: TextStyle(fontWeight: FontWeight.w700, color: c.ink)),
              if (text.isNotEmpty) Text(text, maxLines: 5, overflow: TextOverflow.ellipsis, style: TextStyle(color: c.ink, height: 1.4, fontSize: 14.5)),
              if (text.isEmpty && title.isEmpty) Text('No text', style: TextStyle(color: c.muted, fontStyle: FontStyle.italic)),
            ]),
          ),
          if (media != null) ...[
            const SizedBox(width: 10),
            Stack(children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(9),
                child: media['kind'] == 'image'
                    ? Image.network(app.api.fileUrl(media['url'] as String), headers: app.api.authHeaders, width: 64, height: 64, fit: BoxFit.cover, errorBuilder: (_, _, _) => Container(width: 64, height: 64, color: c.soft, child: Icon(Icons.image_outlined, color: c.muted)))
                    : Container(width: 64, height: 64, color: c.soft, child: Icon(Icons.play_circle_outline, color: c.ink2, size: 28)),
              ),
              if (photos > 1) Positioned(right: 4, bottom: 4, child: Container(padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1), decoration: BoxDecoration(color: Colors.black.withValues(alpha: 0.65), borderRadius: BorderRadius.circular(6)), child: Text('$photos', style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700)))),
            ]),
          ],
        ]),
        if (post['error'] != null && tab == 'failed') ...[const SizedBox(height: 10), Callout(post['error'].toString())],
        if (post['note'] != null && tab == 'drafts') ...[const SizedBox(height: 10), Callout('Note: ${post['note']}', tone: Tone.warn, icon: Icons.chat_bubble_outline)],
        if (stats != null && stats.isNotEmpty) ...[
          const SizedBox(height: 10),
          Wrap(spacing: 14, children: [for (final e in stats.entries) Text('${e.value} ${e.key}', style: TextStyle(color: c.ink2, fontSize: 12.5, fontWeight: FontWeight.w600))]),
        ],
        if (actions.isNotEmpty) ...[
          const SizedBox(height: 12),
          Wrap(spacing: 8, runSpacing: 8, crossAxisAlignment: WrapCrossAlignment.center, children: actions),
        ],
      ]),
    );
  }
}

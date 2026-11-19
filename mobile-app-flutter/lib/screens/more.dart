import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../api.dart';
import '../fx.dart';
import '../motion.dart';
import '../state.dart';
import '../theme.dart';
import '../widgets.dart';
import 'shell.dart';

/// Opens a page of the website signed in, and reloads the app's data when the person comes back.
Future<void> openWebPage(BuildContext context, String path, {bool external = false}) async {
  final app = context.read<AppState>();
  try {
    await app.api.openWeb(path, external: external);
  } on ApiException catch (e) {
    if (context.mounted) showSnack(context, e.message, error: true);
  }
}

class MoreScreen extends StatelessWidget {
  const MoreScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final c = context.c;
    void push(Widget screen) => Navigator.of(context).push(MaterialPageRoute(builder: (_) => screen));
    Widget tile(IconData icon, String title, String? subtitle, VoidCallback action, {bool web = false}) => ListTile(
          leading: Container(width: 34, height: 34, decoration: BoxDecoration(color: c.soft, borderRadius: BorderRadius.circular(10)), child: Icon(icon, size: 19, color: c.ink2)),
          title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
          subtitle: subtitle == null ? null : Text(subtitle, style: TextStyle(color: c.muted, fontSize: 12.5)),
          trailing: Icon(web ? Icons.open_in_new : Icons.chevron_right, size: web ? 18 : 24, color: c.muted),
          onTap: () {
            Fx.tap();
            action();
          },
        );
    Widget group(List<Widget> children) => AppCard(
          padding: EdgeInsets.zero,
          child: Column(children: [for (var i = 0; i < children.length; i++) ...[if (i > 0) Divider(height: 1, indent: 56, color: c.line), children[i]]]),
        );
    final workspaces = app.workspaces;
    return RefreshIndicator(
      onRefresh: app.refresh,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 32),
        children: staggered([
          AppCard(
            child: Row(children: [
              CircleAvatar(radius: 22, backgroundColor: c.greenSoft, child: Text(((app.me['name'] ?? '?').toString().isEmpty ? '?' : app.me['name'].toString()[0]).toUpperCase(), style: TextStyle(color: c.greenInk, fontWeight: FontWeight.w800, fontSize: 18))),
              const SizedBox(width: 12),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text((app.me['name'] ?? '').toString(), style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16, color: c.ink)),
                  Text((app.me['email'] ?? '').toString(), style: TextStyle(color: c.muted, fontSize: 13)),
                ]),
              ),
              Pill('${app.workspace['plan'] ?? 'Free'} plan', tone: app.paid ? Tone.ok : Tone.neutral),
            ]),
          ),
          if (workspaces.length > 1) ...[
            const SectionTitle('Workspace'),
            group([
              for (final w in workspaces)
                ListTile(
                  leading: Icon(w['id'] == app.workspace['id'] ? Icons.radio_button_checked : Icons.radio_button_off, color: w['id'] == app.workspace['id'] ? c.greenInk : c.muted),
                  title: Text(w['name'].toString(), style: const TextStyle(fontWeight: FontWeight.w600)),
                  subtitle: w['role'] == 'owner' ? (w['client'] == true ? const Text('Client workspace') : null) : Text(w['role'].toString()[0].toUpperCase() + w['role'].toString().substring(1)),
                  onTap: () async {
                    Fx.tap();
                    await app.switchWorkspace((w['id'] as num).toInt());
                    if (context.mounted) showSnack(context, 'Switched to ${w['name']}.');
                  },
                ),
            ]),
          ],
          const SectionTitle('Content'),
          group([
            tile(Icons.hub_outlined, 'Channels', '${app.channels.length} connected', () => push(const ChannelsScreen())),
            tile(Icons.photo_library_outlined, 'Media Library', 'Photos and videos to reuse', () => push(const LibraryScreen())),
            tile(Icons.bolt_outlined, 'Automations', 'Blog to social, weekly AI plan, auto-replies', () => push(const AutomationsScreen())),
            tile(Icons.lightbulb_outline, 'Ideas and templates', null, () => openWebPage(context, '/app/create'), web: true),
          ]),
          const SectionTitle('Grow'),
          group([
            tile(Icons.trending_up, 'Competitors', 'Compare with other accounts', () => openWebPage(context, '/app/competitors'), web: true),
            tile(Icons.link, 'Link in Bio', null, () => openWebPage(context, '/app/link-in-bio'), web: true),
          ]),
          const SectionTitle('Workspace settings'),
          group([
            tile(Icons.group_add_outlined, 'Team', 'Invite teammates and set roles', () => openWebPage(context, '/app/team'), web: true),
            tile(Icons.swap_horiz, 'Client workspaces', null, () => openWebPage(context, '/app/workspaces'), web: true),
            if (app.isAdmin) tile(Icons.tune, 'Link tracking and branded reports', null, () => openWebPage(context, '/app/settings'), web: true),
            if (app.isAdmin) tile(Icons.code, 'API & webhooks', null, () => openWebPage(context, '/app/developers'), web: true),
          ]),
          const SectionTitle('You'),
          group([
            tile(Icons.person_outline, 'Account', 'Name, password, email alerts', () => push(const AccountScreen())),
            tile(Icons.workspace_premium_outlined, 'Plan & billing', '${app.workspace['plan'] ?? 'Free'} plan', () => push(const PlanScreen())),
            ListTile(
              leading: Container(width: 34, height: 34, decoration: BoxDecoration(color: c.soft, borderRadius: BorderRadius.circular(10)), child: Icon(Icons.volume_up_outlined, size: 19, color: c.ink2)),
              title: const Text('Sound effects', style: TextStyle(fontWeight: FontWeight.w600)),
              subtitle: Text('Quiet when your phone is on silent', style: TextStyle(color: c.muted, fontSize: 12.5)),
              trailing: Switch(
                value: app.sound,
                onChanged: (on) async {
                  await app.setSound(on);
                  if (on) Fx.toggle();
                },
              ),
            ),
            ListTile(
              leading: Container(width: 34, height: 34, decoration: BoxDecoration(color: c.soft, borderRadius: BorderRadius.circular(10)), child: Icon(Icons.dark_mode_outlined, size: 19, color: c.ink2)),
              title: const Text('Theme', style: TextStyle(fontWeight: FontWeight.w600)),
              trailing: SegmentedButton<ThemeMode>(
                showSelectedIcon: false,
                style: const ButtonStyle(visualDensity: VisualDensity.compact),
                segments: const [ButtonSegment(value: ThemeMode.light, label: Text('Light')), ButtonSegment(value: ThemeMode.system, label: Text('Auto')), ButtonSegment(value: ThemeMode.dark, label: Text('Dark'))],
                selected: {app.themeMode},
                onSelectionChanged: (s) {
                  Fx.toggle();
                  app.setTheme(s.first);
                },
              ),
            ),
            tile(Icons.help_outline, 'Help', null, () => app.api.openLink('https://atwebtech.us/faq'), web: true),
          ]),
          const SizedBox(height: 18),
          OutlinedButton.icon(
            onPressed: () async {
              if (await confirm(context, 'Log out?', 'You’ll need your email and password to come back.', yes: 'Log out', danger: false)) await app.logout();
            },
            icon: const Icon(Icons.logout, size: 18),
            label: const Text('Log out'),
          ),
        ]),
      ),
    );
  }
}

class ChannelsScreen extends StatelessWidget {
  const ChannelsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final c = context.c;
    Future<void> connect() async {
      await openWebPage(context, '/app/channels');
    }
    return Scaffold(
      appBar: AppBar(title: const Text('Channels')),
      floatingActionButton: app.isAdmin ? FloatingActionButton.extended(onPressed: connect, icon: const Icon(Icons.add), label: const Text('Connect', style: TextStyle(fontWeight: FontWeight.w700))) : null,
      body: RefreshIndicator(
        onRefresh: app.refresh,
        child: app.channels.isEmpty
            ? ListView(children: [SizedBox(height: 460, child: EmptyView(icon: Icons.hub_outlined, title: 'No channels yet', text: 'Connecting opens a secure browser, because each network asks you to sign in on its own page.', action: app.isAdmin ? 'Connect a channel' : null, onAction: connect))])
            : ListView(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 96),
                children: [
                  Callout('After connecting or reconnecting in the browser, pull down here to refresh.', tone: Tone.info, icon: Icons.info_outline),
                  const SizedBox(height: 12),
                  for (final (i, ch) in app.channels.indexed)
                    Appear(
                      key: ValueKey('ch-${ch['id']}'),
                      index: i,
                      child: Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: AppCard(
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Row(children: [
                            ChannelAvatar(network: ch['network'] as String, name: ch['name'] as String?),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                Text(ch['name'].toString(), style: TextStyle(fontWeight: FontWeight.w700, color: c.ink)),
                                Text('${app.network(ch['network'] as String)['name']} · ${ch['times']} posting times a week', style: TextStyle(color: c.muted, fontSize: 12.5)),
                              ]),
                            ),
                            Pill(ch['paused'] == true ? 'Paused' : (ch['can_publish'] == true ? 'Publishing' : 'Plan only'), tone: ch['paused'] == true ? Tone.warn : (ch['can_publish'] == true ? Tone.ok : Tone.neutral)),
                          ]),
                          if (ch['problem'] != null) ...[const SizedBox(height: 10), Callout(ch['problem'].toString(), tone: Tone.warn, icon: Icons.link_off)],
                          if (app.isAdmin) ...[
                            const SizedBox(height: 12),
                            Wrap(spacing: 8, runSpacing: 8, children: [
                              SmallButton(
                                label: ch['paused'] == true ? 'Resume queue' : 'Pause queue', icon: ch['paused'] == true ? Icons.play_arrow : Icons.pause,
                                onPressed: () async {
                                  if (await act(context, () => app.api.postForm('/app/channels/${ch['id']}/queue-tools', {'action': 'pause', 'paused': ch['paused'] == true ? '' : '1'}))) await app.refresh();
                                },
                              ),
                              SmallButton(label: 'Schedule and settings', icon: Icons.open_in_new, onPressed: () => openWebPage(context, '/app/channels/${ch['id']}/settings')),
                            ]),
                          ],
                        ]),
                      ),
                    )),
                ],
              ),
      ),
    );
  }
}

class AutomationsScreen extends StatefulWidget {
  const AutomationsScreen({super.key});

  @override
  State<AutomationsScreen> createState() => _AutomationsScreenState();
}

class _AutomationsScreenState extends State<AutomationsScreen> {
  final _loader = GlobalKey<LoadedState<Map<String, dynamic>>>();

  @override
  Widget build(BuildContext context) {
    final app = context.read<AppState>();
    final c = context.c;
    Future<void> run(Map<String, dynamic> fields) async {
      if (await act(context, () => app.api.postForm('/app/automations', fields))) await _loader.currentState?.reload();
    }
    return Scaffold(
      appBar: AppBar(title: const Text('Automations')),
      body: Loaded<Map<String, dynamic>>(
        key: _loader,
        load: () => app.api.get('/api/mobile/automations'),
        builder: (context, d, reload) {
          final items = (d['items'] as List).map((e) => Map<String, dynamic>.from(e as Map)).toList();
          final canEdit = d['can_edit'] == true;
          return ListView(
            padding: const EdgeInsets.all(16),
            children: staggered([
              AppCard(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('Let atwebtech do the weekly work', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700, color: c.ink)),
                  const SizedBox(height: 4),
                  Text(d['paid'] == true ? 'Turn your blog into posts, have the AI Assistant write next week’s plan, and answer common questions automatically.' : 'On Essentials and Team, your blog turns into posts, the AI Assistant writes next week’s plan, and common questions get answered.', style: TextStyle(color: c.ink2, height: 1.4)),
                  const SizedBox(height: 14),
                  if (d['paid'] != true)
                    FilledButton(onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const PlanScreen())), child: const Text('See plans'))
                  else if (canEdit)
                    FilledButton.icon(onPressed: () => openWebPage(context, '/app/automations'), icon: const Icon(Icons.add), label: const Text('Set up an automation')),
                ]),
              ),
              const SectionTitle('Your automations'),
              if (items.isEmpty) Text('None yet.', style: TextStyle(color: c.muted)),
              for (final a in items)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: AppCard(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Row(children: [
                        Icon(switch (a['kind']) { 'feed' => Icons.rss_feed, 'plan' => Icons.auto_awesome, _ => Icons.quickreply_outlined }, color: c.greenInk),
                        const SizedBox(width: 10),
                        Expanded(child: Text(a['name'].toString(), style: TextStyle(fontWeight: FontWeight.w700, color: c.ink))),
                        if (canEdit) Switch(value: a['enabled'] == true, onChanged: (_) { Fx.toggle(); run({'action': 'toggle', 'id': a['id']}); }) else Pill(a['enabled'] == true ? 'On' : 'Paused', tone: a['enabled'] == true ? Tone.ok : Tone.neutral),
                      ]),
                      Text(a['summary'].toString(), style: TextStyle(color: c.ink2, fontSize: 13.5)),
                      const SizedBox(height: 4),
                      Text('${a['made']} ${a['kind'] == 'reply' ? 'replies' : 'posts'} so far${a['result'] != null ? ' · ${a['result']}' : ''}', style: TextStyle(color: c.muted, fontSize: 12.5)),
                      if (canEdit) ...[
                        const SizedBox(height: 10),
                        Wrap(spacing: 8, children: [
                          if (a['kind'] != 'reply' && a['enabled'] == true && d['paid'] == true) SmallButton(label: 'Run now', icon: Icons.play_arrow, onPressed: () => run({'action': 'run', 'id': a['id']})),
                          SmallButton(label: 'Delete', danger: true, onPressed: () async {
                            if (await confirm(context, 'Delete this automation?', 'Posts it already made stay.')) await run({'action': 'delete', 'id': a['id']});
                          }),
                        ]),
                      ],
                    ]),
                  ),
                ),
            ]),
          );
        },
      ),
    );
  }
}

class LibraryScreen extends StatefulWidget {
  const LibraryScreen({super.key});

  @override
  State<LibraryScreen> createState() => _LibraryScreenState();
}

class _LibraryScreenState extends State<LibraryScreen> {
  final _loader = GlobalKey<LoadedState<Map<String, dynamic>>>();
  double? _progress;

  Future<void> _upload() async {
    final app = context.read<AppState>();
    try {
      final files = await ImagePicker().pickMultipleMedia(imageQuality: 88, maxWidth: 2400, limit: 10);
      if (files.isEmpty) return;
      final ids = <int>[];
      for (final f in files) {
        setState(() => _progress = 0);
        final media = await app.api.upload(File(f.path), f.name, (p) { if (mounted) setState(() => _progress = p); });
        ids.add((media['id'] as num).toInt());
      }
      // Uploads made here are kept even when no post uses them.
      await app.api.postForm('/app/library', {'action': 'keep', 'ids': ids});
      if (mounted) showSnack(context, ids.length == 1 ? 'Added to your library.' : '${ids.length} files added to your library.');
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, error: true);
    } catch (_) {
      if (mounted) showSnack(context, 'Couldn’t open your photos. Allow access in Settings and try again.', error: true);
    } finally {
      if (mounted) setState(() => _progress = null);
      await _loader.currentState?.reload();
    }
  }

  void _menu(Map<String, dynamic> m) {
    final app = context.read<AppState>();
    showModalBottomSheet<void>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          ListTile(title: Text(m['name'].toString(), maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700)), subtitle: Text('${m['date']} · ${m['used'] == 0 ? 'not in a post' : 'in ${m['used']} posts'}')),
          ListTile(leading: const Icon(Icons.edit_note), title: const Text('Create a post with it'), onTap: () { Navigator.pop(ctx); Shell.compose(context, prefill: {'media': m}); }),
          ListTile(
            leading: Icon(m['kept'] == true ? Icons.bookmark_remove_outlined : Icons.bookmark_add_outlined),
            title: Text(m['kept'] == true ? 'Stop keeping it' : 'Keep in library'),
            onTap: () async {
              Navigator.pop(ctx);
              if (await act(context, () => app.api.postForm('/app/library', {'action': m['kept'] == true ? 'release' : 'keep', 'id': m['id']}))) await _loader.currentState?.reload();
            },
          ),
          ListTile(
            leading: Icon(Icons.delete_outline, color: context.c.bad),
            title: Text('Delete', style: TextStyle(color: context.c.bad)),
            onTap: () async {
              Navigator.pop(ctx);
              if (!await confirm(context, 'Delete this file?', 'Sent posts keep their link, but it can’t be reused.')) return;
              if (mounted && await act(context, () => app.api.postForm('/app/library', {'action': 'delete', 'id': m['id']}))) await _loader.currentState?.reload();
            },
          ),
          const SizedBox(height: 8),
        ]),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final app = context.read<AppState>();
    final c = context.c;
    return Scaffold(
      appBar: AppBar(title: const Text('Media Library')),
      floatingActionButton: FloatingActionButton.extended(onPressed: _progress == null ? _upload : null, icon: const Icon(Icons.upload), label: Text(_progress == null ? 'Upload' : '${(_progress! * 100).round()}%', style: const TextStyle(fontWeight: FontWeight.w700))),
      body: Loaded<Map<String, dynamic>>(
        key: _loader,
        load: () => app.api.get('/api/mobile/library'),
        builder: (context, d, reload) {
          final items = (d['items'] as List).map((e) => Map<String, dynamic>.from(e as Map)).toList();
          if (items.isEmpty) {
            return ListView(children: const [SizedBox(height: 460, child: EmptyView(icon: Icons.photo_library_outlined, title: 'Your library is empty', text: 'Upload photos and videos here, or attach them to a post, and they’ll show up for reuse.'))]);
          }
          final used = (d['used_bytes'] as num) / (1024 * 1024);
          return CustomScrollView(slivers: [
            SliverPadding(padding: const EdgeInsets.fromLTRB(16, 12, 16, 0), sliver: SliverToBoxAdapter(child: Text('${used.toStringAsFixed(1)} MB of 2 GB used', style: TextStyle(color: c.muted, fontSize: 12.5)))),
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 96),
              sliver: SliverGrid.count(
                crossAxisCount: 3, crossAxisSpacing: 8, mainAxisSpacing: 8,
                children: [
                  for (final (i, m) in items.indexed)
                    Appear(
                      key: ValueKey('m-${m['id']}'),
                      index: i,
                      scale: true,
                      child: InkWell(
                      onTap: () { Fx.tap(); _menu(m); },
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(10),
                        child: Stack(fit: StackFit.expand, children: [
                          m['kind'] == 'image'
                              ? Image.network(app.api.fileUrl(m['url'] as String), headers: app.api.authHeaders, fit: BoxFit.cover, errorBuilder: (_, _, _) => Container(color: c.soft, child: Icon(Icons.image_outlined, color: c.muted)))
                              : Container(color: c.soft, child: Icon(Icons.play_circle_outline, color: c.ink2, size: 34)),
                          if (m['kept'] == true) const Positioned(top: 6, right: 6, child: Icon(Icons.bookmark, color: Colors.white, size: 18, shadows: [Shadow(blurRadius: 4)])),
                        ]),
                      ),
                    )),
                ],
              ),
            ),
          ]);
        },
      ),
    );
  }
}

class AccountScreen extends StatefulWidget {
  const AccountScreen({super.key});

  @override
  State<AccountScreen> createState() => _AccountScreenState();
}

class _AccountScreenState extends State<AccountScreen> {
  final _loader = GlobalKey<LoadedState<Map<String, dynamic>>>();
  final _name = TextEditingController();
  final _current = TextEditingController();
  final _next = TextEditingController();
  Set<String>? _email;

  @override
  void dispose() {
    _name.dispose();
    _current.dispose();
    _next.dispose();
    super.dispose();
  }

  /// Asks for the password and the word DELETE, then deletes the account on the server and signs out.
  Future<void> _deleteAccount() async {
    final app = context.read<AppState>();
    final messenger = ScaffoldMessenger.of(context);
    final navigator = Navigator.of(context);
    final password = TextEditingController();
    final word = TextEditingController();
    final go = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete your account?'),
        content: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Everything in it is deleted for good and you are signed out on every device.'),
          const SizedBox(height: 14),
          TextField(controller: password, obscureText: true, decoration: const InputDecoration(labelText: 'Your password')),
          const SizedBox(height: 10),
          TextField(controller: word, autocorrect: false, textCapitalization: TextCapitalization.characters, decoration: const InputDecoration(labelText: 'Type DELETE to confirm')),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.pop(ctx, true), style: TextButton.styleFrom(foregroundColor: ctx.c.bad), child: const Text('Delete account')),
        ],
      ),
    );
    if (go != true || !mounted) return;
    try {
      final message = await app.deleteAccount(password.text, word.text);
      // The app is back on the sign-in screen now: close this page and say what happened there.
      navigator.popUntil((route) => route.isFirst);
      // Once the sign-in screen has finished fading in (two screens can't share one message).
      await Future<void>.delayed(const Duration(milliseconds: 700));
      messenger.showSnackBar(SnackBar(content: Text(message)));
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.detail, error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = context.read<AppState>();
    final c = context.c;
    return Scaffold(
      appBar: AppBar(title: const Text('Account')),
      body: Loaded<Map<String, dynamic>>(
        key: _loader,
        load: () async {
          final d = await app.api.get('/api/mobile/account');
          _name.text = (d['name'] ?? '').toString();
          _email = {for (final n in d['notifications'] as List) if ((n as Map)['email'] == true) n['kind'].toString()};
          return d;
        },
        builder: (context, d, reload) {
          final kinds = (d['notifications'] as List).map((e) => Map<String, dynamic>.from(e as Map)).toList();
          return ListView(
            padding: const EdgeInsets.all(16),
            children: staggered([
              const SectionTitle('Name'),
              Row(children: [
                Expanded(child: TextField(controller: _name, maxLength: 60, decoration: const InputDecoration(counterText: ''))),
                const SizedBox(width: 8),
                FilledButton(onPressed: () async {
                  if (await act(context, () => app.api.postForm('/app/account/profile', {'display_name': _name.text.trim()}))) await app.refresh();
                }, child: const Text('Save')),
              ]),
              const SizedBox(height: 4),
              Text(d['email'].toString(), style: TextStyle(color: c.muted)),
              const SectionTitle('Password'),
              TextField(controller: _current, obscureText: true, decoration: const InputDecoration(labelText: 'Current password')),
              const SizedBox(height: 10),
              TextField(controller: _next, obscureText: true, decoration: const InputDecoration(labelText: 'New password', helperText: '8+ characters, an uppercase letter and a number or symbol')),
              const SizedBox(height: 10),
              OutlinedButton(
                onPressed: () async {
                  final ok = await act(context, () => app.api.postForm('/app/account/password', {'current': _current.text, 'password': _next.text}));
                  if (ok) { _current.clear(); _next.clear(); }
                },
                child: const Text('Change password'),
              ),
              Text('Changing it signs you out on every other device.', style: TextStyle(color: c.muted, fontSize: 12.5)),
              const SectionTitle('Email me when'),
              AppCard(
                padding: EdgeInsets.zero,
                child: Column(children: [
                  for (final k in kinds)
                    SwitchListTile(
                      title: Text(k['label'].toString(), style: const TextStyle(fontSize: 14.5)),
                      value: _email!.contains(k['kind']),
                      onChanged: (on) async {
                        Fx.toggle();
                        setState(() => on ? _email!.add(k['kind'].toString()) : _email!.remove(k['kind']));
                        await act(context, () => app.api.postForm('/app/account/notifications', {'email': _email!.toList()}), done: '');
                      },
                    ),
                ]),
              ),
              Padding(padding: const EdgeInsets.only(top: 6), child: Text('Push notifications follow your phone’s settings for atwebtech.', style: TextStyle(color: c.muted, fontSize: 12.5))),
              const SectionTitle('Security'),
              AppCard(
                padding: EdgeInsets.zero,
                child: ListTile(
                  leading: const Icon(Icons.shield_outlined),
                  title: const Text('Two-factor authentication'),
                  subtitle: Text(d['two_factor'] == true ? 'On' : 'Off'),
                  trailing: const Icon(Icons.open_in_new, size: 18),
                  onTap: () => openWebPage(context, '/app/account'),
                ),
              ),
              const SectionTitle('Delete account'),
              Text('Deletes your account, your workspace and any client workspaces, with every channel, post, photo, video and comment in them. A paid plan is cancelled first. It can’t be undone.', style: TextStyle(color: c.muted, fontSize: 13, height: 1.4)),
              const SizedBox(height: 10),
              Align(alignment: Alignment.centerLeft, child: SmallButton(label: 'Delete my account', icon: Icons.delete_forever_outlined, danger: true, onPressed: _deleteAccount)),
              const SizedBox(height: 24),
            ]),
          );
        },
      ),
    );
  }
}

class PlanScreen extends StatelessWidget {
  const PlanScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final app = context.read<AppState>();
    final c = context.c;
    return Scaffold(
      appBar: AppBar(title: const Text('Plan & billing')),
      body: Loaded<Map<String, dynamic>>(
        load: () => app.api.get('/api/mobile/account'),
        builder: (context, d, reload) {
          final plan = Map<String, dynamic>.from(d['plan'] as Map);
          final options = (plan['options'] as List).map((e) => Map<String, dynamic>.from(e as Map)).toList();
          final status = [
            if (plan['source'] == 'admin') 'Complimentary plan',
            if (plan['status'] != null) plan['canceling'] == true ? 'Canceling' : plan['status'].toString(),
            if (plan['renews'] != null) '${plan['canceling'] == true ? 'Ends' : 'Renews'} ${plan['renews']}',
          ].join(' · ');
          return ListView(
            padding: const EdgeInsets.all(16),
            children: staggered([
              AppCard(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('Current plan', style: TextStyle(color: c.muted, fontSize: 12.5)),
                  Text(plan['name'].toString(), style: TextStyle(fontSize: 26, fontWeight: FontWeight.w800, color: c.ink)),
                  if (status.isNotEmpty) Text(status, style: TextStyle(color: c.ink2)),
                  const SizedBox(height: 4),
                  Text('${plan['channels_used']} of ${plan['source'] == 'admin' ? 'unlimited' : plan['channel_limit']} channels used', style: TextStyle(color: c.muted)),
                ]),
              ),
              const SectionTitle('Plans'),
              for (final o in options)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: AppCard(
                    accent: o['slug'] == plan['slug'] ? c.greenInk : null,
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Row(children: [
                        Expanded(child: Text(o['name'].toString(), style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: c.ink))),
                        if (o['slug'] == plan['slug']) const Pill('Your plan', tone: Tone.ok) else if (o['badge'] != null) Pill(o['badge'].toString(), tone: Tone.info),
                      ]),
                      Text((o['rate'] as num) == 0 ? 'Free' : '\$${(o['rate'] as num).toStringAsFixed(0)} per channel a month · yearly billing saves ${12 - (plan['yearly_months'] as num).toInt()} months', style: TextStyle(color: c.ink2)),
                      const SizedBox(height: 8),
                      for (final f in (o['features'] as List).take(6))
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 2),
                          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Icon(Icons.check, size: 16, color: c.greenInk), const SizedBox(width: 8), Expanded(child: Text(f.toString(), style: TextStyle(color: c.ink2, fontSize: 13.5)))]),
                        ),
                    ]),
                  ),
                ),
              const SizedBox(height: 6),
              if (plan['billing_enabled'] == true) ...[
                FilledButton.icon(onPressed: () => openWebPage(context, '/app/billing', external: true), icon: const Icon(Icons.open_in_new, size: 18), label: Text(plan['paid'] == true && plan['source'] != 'admin' ? 'Manage plan and payment' : 'Upgrade on atwebtech.us')),
                const SizedBox(height: 8),
                Text('Payments are handled on the atwebtech website by Stripe. It opens in your browser, already signed in. Pull down here afterwards to see your new plan.', style: TextStyle(color: c.muted, fontSize: 12.5, height: 1.4)),
              ] else
                const Callout('Paid plans aren’t open for purchase yet.', tone: Tone.info, icon: Icons.info_outline),
            ]),
          );
        },
      ),
    );
  }
}

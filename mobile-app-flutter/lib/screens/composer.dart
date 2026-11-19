import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../api.dart';
import '../state.dart';
import '../theme.dart';
import '../widgets.dart';

/// Write a post: channels, text, photos or a video (carousels too), time, repeat, and AI help.
class ComposerScreen extends StatefulWidget {
  const ComposerScreen({super.key, this.postId, this.prefill});
  final int? postId;
  final Map<String, dynamic>? prefill;

  @override
  State<ComposerScreen> createState() => _ComposerScreenState();
}

class _ComposerScreenState extends State<ComposerScreen> {
  final _body = TextEditingController();
  final _title = TextEditingController();
  final _picker = ImagePicker();
  final Set<String> _targets = {};
  Map<String, dynamic>? _media;
  final List<Map<String, dynamic>> _extras = [];
  late DateTime _when;
  String _repeat = '';
  bool _loading = false;
  bool _busy = false;
  double? _progress;
  String? _error;

  bool get _editing => widget.postId != null;

  @override
  void initState() {
    super.initState();
    final app = context.read<AppState>();
    final now = app.workspaceNow.add(const Duration(hours: 1));
    _when = DateTime(now.year, now.month, now.day, now.hour);
    final pre = widget.prefill;
    if (pre != null) {
      _body.text = (pre['body'] ?? '').toString();
      if (pre['media'] is Map) _media = Map<String, dynamic>.from(pre['media'] as Map);
      if (pre['target'] != null) _targets.add(pre['target'].toString());
    }
    if (_editing) {
      _loading = true;
      _loadPost();
    } else if (_targets.isEmpty) {
      final publishable = app.channels.where((ch) => ch['can_publish'] == true).toList();
      if (publishable.length == 1) _targets.add('c:${publishable.first['id']}');
    }
    _body.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _body.dispose();
    _title.dispose();
    super.dispose();
  }

  Future<void> _loadPost() async {
    try {
      final res = await context.read<AppState>().api.get('/api/mobile/posts/${widget.postId}');
      final p = Map<String, dynamic>.from(res['post'] as Map);
      if (!mounted) return;
      setState(() {
        _body.text = (p['body'] ?? '').toString();
        _title.text = (p['title'] ?? '').toString();
        _targets
          ..clear()
          ..addAll((p['targets'] as List? ?? []).map((e) => e.toString()));
        _media = p['media'] is Map ? Map<String, dynamic>.from(p['media'] as Map) : null;
        _extras
          ..clear()
          ..addAll((p['extra_media'] as List? ?? []).map((e) => Map<String, dynamic>.from(e as Map)));
        _repeat = (p['repeat'] ?? '').toString();
        final date = (p['date'] ?? '').toString();
        final time = (p['time'] ?? '').toString();
        if (date.isNotEmpty && time.isNotEmpty) {
          final parsed = DateTime.tryParse('${date}T$time:00');
          if (parsed != null) _when = parsed;
        }
        _loading = false;
      });
    } on ApiException catch (e) {
      if (mounted) setState(() { _error = e.message; _loading = false; });
    }
  }

  List<Map<String, dynamic>> get _picked {
    final app = context.read<AppState>();
    return app.channels.where((ch) => _targets.contains('c:${ch['id']}')).toList();
  }

  int get _limit {
    final app = context.read<AppState>();
    final limits = _picked.map((ch) => (app.network(ch['network'] as String)['limit'] as num).toInt());
    return limits.isEmpty ? 5000 : limits.reduce((a, b) => a < b ? a : b);
  }

  bool get _hasYoutube => _picked.any((ch) => ch['network'] == 'youtube');

  Future<Map<String, dynamic>?> _upload(XFile file) async {
    final api = context.read<AppState>().api;
    setState(() { _progress = 0; _error = null; });
    try {
      return await api.upload(File(file.path), file.name, (p) { if (mounted) setState(() => _progress = p); });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
      return null;
    } finally {
      if (mounted) setState(() => _progress = null);
    }
  }

  Future<void> _pick({required bool video, ImageSource source = ImageSource.gallery}) async {
    try {
      final file = video ? await _picker.pickVideo(source: source, maxDuration: const Duration(minutes: 10)) : await _picker.pickImage(source: source, imageQuality: 88, maxWidth: 2400);
      if (file == null) return;
      final media = await _upload(file);
      if (media != null && mounted) setState(() { _media = media; _extras.clear(); });
    } catch (_) {
      if (mounted) setState(() => _error = 'Couldn’t open your photos. Allow access in Settings and try again.');
    }
  }

  Future<void> _addPhotos() async {
    final room = 9 - _extras.length;
    if (room <= 0) return;
    try {
      final files = await _picker.pickMultiImage(imageQuality: 88, maxWidth: 2400, limit: room < 2 ? 2 : room);
      for (final file in files.take(room)) {
        final media = await _upload(file);
        if (media != null && mounted) setState(() => _extras.add(media));
      }
    } catch (_) {
      if (mounted) setState(() => _error = 'Couldn’t open your photos. Allow access in Settings and try again.');
    }
  }

  void _attach() {
    showModalBottomSheet<void>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          ListTile(leading: const Icon(Icons.photo_outlined), title: const Text('Photo from library'), onTap: () { Navigator.pop(ctx); _pick(video: false); }),
          ListTile(leading: const Icon(Icons.photo_camera_outlined), title: const Text('Take a photo'), onTap: () { Navigator.pop(ctx); _pick(video: false, source: ImageSource.camera); }),
          ListTile(leading: const Icon(Icons.videocam_outlined), title: const Text('Video from library'), subtitle: const Text('Up to 200 MB'), onTap: () { Navigator.pop(ctx); _pick(video: true); }),
          const SizedBox(height: 8),
        ]),
      ),
    );
  }

  Future<void> _ai() async {
    final app = context.read<AppState>();
    final prompt = TextEditingController();
    final go = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Write with AI'),
        content: TextField(controller: prompt, maxLines: 4, autofocus: true, decoration: const InputDecoration(hintText: 'What is the post about? For example: our new autumn menu, friendly, with a question at the end')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Write it')),
        ],
      ),
    );
    if (go != true || prompt.text.trim().isEmpty || !mounted) return;
    setState(() => _busy = true);
    try {
      final network = _picked.isEmpty ? '' : _picked.first['network'] as String;
      final res = await app.api.postForm('/app/ai/generate', {'prompt': prompt.text.trim(), 'network': network});
      final text = (res['text'] ?? '').toString();
      if (mounted && text.isNotEmpty) setState(() => _body.text = _body.text.trim().isEmpty ? text : '${_body.text.trimRight()}\n\n$text');
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _pickWhen() async {
    final now = context.read<AppState>().workspaceNow;
    final date = await showDatePicker(context: context, initialDate: _when.isBefore(now) ? now : _when, firstDate: DateTime(now.year, now.month, now.day), lastDate: now.add(const Duration(days: 365)));
    if (date == null || !mounted) return;
    final time = await showTimePicker(context: context, initialTime: TimeOfDay(hour: _when.hour, minute: _when.minute));
    if (time == null) return;
    setState(() => _when = DateTime(date.year, date.month, date.day, time.hour, time.minute));
  }

  String _two(int n) => n.toString().padLeft(2, '0');

  String get _whenLabel {
    const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    final h = _when.hour % 12 == 0 ? 12 : _when.hour % 12;
    return '${days[_when.weekday - 1]} ${_when.day} ${months[_when.month - 1]}, $h:${_two(_when.minute)} ${_when.hour < 12 ? 'AM' : 'PM'}';
  }

  Future<void> _submit(String action) async {
    final app = context.read<AppState>();
    if (_targets.isEmpty) {
      setState(() => _error = 'Choose at least one channel.');
      return;
    }
    setState(() { _busy = true; _error = null; });
    try {
      final res = await app.api.postForm('/app/posts', {
        'action': action,
        if (_editing) 'id': widget.postId,
        'targets': _targets.toList(),
        'body': _body.text,
        'title': _title.text,
        'media_id': _media?['id'] ?? 0,
        'extra_media': _extras.map((m) => m['id']).join(','),
        'date': '${_when.year}-${_two(_when.month)}-${_two(_when.day)}',
        'time': '${_two(_when.hour)}:${_two(_when.minute)}',
        'repeat': _repeat,
      });
      if (!mounted) return;
      showSnack(context, res['message']?.toString());
      Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.detail);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final c = context.c;
    final left = _limit - _body.text.characters.length;
    final repeats = Map<String, dynamic>.from(app.boot?['repeats'] as Map? ?? {'': 'Doesn’t repeat'});
    final uploading = _progress != null;
    final blocked = _busy || uploading || left < 0;
    return Scaffold(
      appBar: AppBar(
        title: Text(_editing ? 'Edit post' : 'New post'),
        actions: [
          if (app.aiEnabled) TextButton.icon(onPressed: _busy ? null : _ai, icon: const Icon(Icons.auto_awesome, size: 18), label: const Text('AI')),
          const SizedBox(width: 6),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : SafeArea(
              child: Column(children: [
                Expanded(
                  child: ListView(padding: const EdgeInsets.all(16), children: [
                    if (_error != null) ...[Callout(_error!), const SizedBox(height: 12)],
                    Text('Post to', style: TextStyle(color: c.ink2, fontWeight: FontWeight.w700, fontSize: 13)),
                    const SizedBox(height: 8),
                    if (app.channels.isEmpty)
                      const Callout('Connect a channel first (More → Channels).', tone: Tone.warn, icon: Icons.hub_outlined)
                    else
                      Wrap(spacing: 8, runSpacing: 6, children: [
                        for (final ch in app.channels)
                          FilterChip(
                            avatar: ChannelAvatar(network: ch['network'] as String, name: ch['name'] as String?, size: 22),
                            label: Text(ch['name'].toString(), overflow: TextOverflow.ellipsis),
                            selected: _targets.contains('c:${ch['id']}'),
                            onSelected: (on) => setState(() {
                              if (_editing) _targets.clear();
                              on ? _targets.add('c:${ch['id']}') : _targets.remove('c:${ch['id']}');
                            }),
                          ),
                      ]),
                    if (_picked.any((ch) => ch['can_publish'] != true)) ...[
                      const SizedBox(height: 8),
                      const Callout('One of these channels can’t publish yet. Its post is saved as a plan only. Reconnect it under More → Channels.', tone: Tone.warn, icon: Icons.info_outline),
                    ],
                    const SizedBox(height: 16),
                    if (_hasYoutube) ...[
                      TextField(controller: _title, maxLength: 100, decoration: const InputDecoration(labelText: 'Video title (YouTube)')),
                      const SizedBox(height: 8),
                    ],
                    TextField(
                      controller: _body, minLines: 6, maxLines: 14, textCapitalization: TextCapitalization.sentences,
                      decoration: const InputDecoration(hintText: 'What do you want to share?', alignLabelWithHint: true),
                    ),
                    const SizedBox(height: 6),
                    Align(alignment: Alignment.centerRight, child: Text(left >= 0 ? '$left left' : '${-left} over the limit', style: TextStyle(color: left < 0 ? c.bad : c.muted, fontSize: 12.5, fontWeight: left < 0 ? FontWeight.w700 : FontWeight.w400))),
                    const SizedBox(height: 10),
                    if (uploading) ...[
                      LinearProgressIndicator(value: _progress == 0 ? null : _progress, borderRadius: BorderRadius.circular(4)),
                      const SizedBox(height: 6),
                      Text('Uploading… ${((_progress ?? 0) * 100).round()}%', style: TextStyle(color: c.muted, fontSize: 12.5)),
                      const SizedBox(height: 10),
                    ],
                    if (_media == null)
                      OutlinedButton.icon(onPressed: uploading ? null : _attach, icon: const Icon(Icons.add_photo_alternate_outlined), label: const Text('Add a photo or video'))
                    else
                      SizedBox(
                        height: 84,
                        child: ListView(scrollDirection: Axis.horizontal, children: [
                          _Thumb(media: _media!, onRemove: () => setState(() { _media = null; _extras.clear(); })),
                          for (var i = 0; i < _extras.length; i++) _Thumb(media: _extras[i], onRemove: () => setState(() => _extras.removeAt(i))),
                          if (_media!['kind'] == 'image' && _extras.length < 9)
                            InkWell(
                              onTap: uploading ? null : _addPhotos,
                              borderRadius: BorderRadius.circular(10),
                              child: Container(
                                width: 84, margin: const EdgeInsets.only(right: 8),
                                decoration: BoxDecoration(border: Border.all(color: c.line), borderRadius: BorderRadius.circular(10)),
                                child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [Icon(Icons.add, color: c.ink2), Text('Carousel', style: TextStyle(fontSize: 11.5, color: c.muted))]),
                              ),
                            ),
                        ]),
                      ),
                    const SizedBox(height: 18),
                    AppCard(
                      padding: EdgeInsets.zero,
                      child: Column(children: [
                        ListTile(leading: const Icon(Icons.schedule), title: const Text('When'), subtitle: Text('$_whenLabel · ${app.workspace['timezone'] ?? ''}'), trailing: const Icon(Icons.chevron_right), onTap: _pickWhen),
                        Divider(height: 1, color: c.line),
                        ListTile(
                          leading: const Icon(Icons.repeat),
                          title: const Text('Repeat'),
                          trailing: DropdownButton<String>(
                            value: repeats.containsKey(_repeat) ? _repeat : '',
                            underline: const SizedBox.shrink(),
                            items: [for (final e in repeats.entries) DropdownMenuItem(value: e.key, child: Text(e.value.toString().replaceFirst('Repeats every', 'Every'), style: const TextStyle(fontSize: 14)))],
                            onChanged: (v) => setState(() => _repeat = v ?? ''),
                          ),
                        ),
                      ]),
                    ),
                  ]),
                ),
                Container(
                  padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
                  decoration: BoxDecoration(color: c.panel, border: Border(top: BorderSide(color: c.line))),
                  child: Row(children: [
                    OutlinedButton(onPressed: blocked ? null : () => _submit('draft'), child: const Text('Save draft')),
                    const SizedBox(width: 8),
                    if (app.canSchedule) ...[
                      IconButton.outlined(tooltip: 'Send for approval', onPressed: blocked ? null : () => _submit('review'), icon: const Icon(Icons.verified_outlined)),
                      const SizedBox(width: 8),
                    ],
                    Expanded(
                      child: FilledButton(
                        onPressed: blocked ? null : () => _submit(app.canSchedule ? 'plan' : 'review'),
                        child: _busy ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2.4)) : Text(app.canSchedule ? 'Schedule' : 'Send for approval'),
                      ),
                    ),
                  ]),
                ),
              ]),
            ),
    );
  }
}

class _Thumb extends StatelessWidget {
  const _Thumb({required this.media, required this.onRemove});
  final Map<String, dynamic> media;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final app = context.read<AppState>();
    final c = context.c;
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: Stack(children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(10),
          child: media['kind'] == 'image'
              ? Image.network(app.api.fileUrl(media['url'] as String), headers: app.api.authHeaders, width: 84, height: 84, fit: BoxFit.cover, errorBuilder: (_, _, _) => Container(width: 84, height: 84, color: c.soft, child: Icon(Icons.image_outlined, color: c.muted)))
              : Container(width: 84, height: 84, color: c.soft, child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [Icon(Icons.play_circle_outline, color: c.ink2, size: 30), Text('Video', style: TextStyle(fontSize: 11.5, color: c.muted))])),
        ),
        Positioned(
          top: 4, right: 4,
          child: InkWell(
            onTap: onRemove,
            child: Container(width: 22, height: 22, decoration: BoxDecoration(color: Colors.black.withValues(alpha: 0.65), shape: BoxShape.circle), child: const Icon(Icons.close, size: 14, color: Colors.white)),
          ),
        ),
      ]),
    );
  }
}

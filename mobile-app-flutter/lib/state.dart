import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'api.dart';
import 'fx.dart';

/// App-wide state: who is signed in, which workspace is open, and the data the shell shows.
class AppState extends ChangeNotifier {
  AppState() {
    api.onUnauthorized = () => _signedOut();
  }

  final api = Api();
  final _storage = const FlutterSecureStorage();

  bool ready = false;
  Map<String, dynamic>? boot;
  ThemeMode themeMode = ThemeMode.system;
  bool sound = true;
  String? loadError;

  bool get signedIn => api.token != null;
  Map<String, dynamic> get me => Map<String, dynamic>.from(boot?['me'] as Map? ?? {});
  Map<String, dynamic> get workspace => Map<String, dynamic>.from(boot?['workspace'] as Map? ?? {});
  Map<String, dynamic> get counts => Map<String, dynamic>.from(boot?['counts'] as Map? ?? {});
  List<Map<String, dynamic>> get channels => _list(boot?['channels']);
  List<Map<String, dynamic>> get workspaces => _list(boot?['workspaces']);
  List<Map<String, dynamic>> get todo => _list(boot?['todo']);
  Map<String, dynamic> get networks => Map<String, dynamic>.from(boot?['networks'] as Map? ?? {});
  bool get paid => workspace['paid'] == true;
  bool get aiEnabled => boot?['ai'] == true;
  String get role => (workspace['role'] ?? 'owner').toString();
  bool get canSchedule => role != 'contributor';
  bool get isAdmin => role == 'owner' || role == 'admin';

  int count(String key) => (counts[key] as num?)?.toInt() ?? 0;

  static List<Map<String, dynamic>> _list(dynamic v) => v is List ? v.map((e) => Map<String, dynamic>.from(e as Map)).toList() : [];

  Map<String, dynamic> network(String slug) => Map<String, dynamic>.from(networks[slug] as Map? ?? {'name': slug, 'color': '#64665F', 'limit': 5000, 'text_only_ok': true});

  /// "Now" on the workspace's clock, so times match the website.
  DateTime get workspaceNow => DateTime.now().toUtc().add(Duration(minutes: (workspace['tz_offset_minutes'] as num?)?.toInt() ?? 0));

  Future<void> start() async {
    // The splash stays for a moment even when loading is instant, so it never just flashes by.
    // Counted from the moment the splash is really on screen, not from when the app began loading.
    final splash = WidgetsBinding.instance.waitUntilFirstFrameRasterized.then((_) => Future<void>.delayed(const Duration(milliseconds: 1600)));
    try {
      api.token = await _storage.read(key: 'token');
      final ws = await _storage.read(key: 'workspace');
      api.workspaceId = ws == null ? null : int.tryParse(ws);
      final theme = await _storage.read(key: 'theme');
      themeMode = switch (theme) { 'light' => ThemeMode.light, 'dark' => ThemeMode.dark, _ => ThemeMode.system };
      sound = await _storage.read(key: 'sound') != 'off';
      Fx.enabled = sound;
    } catch (_) {}
    if (signedIn) {
      await refresh(silent: true);
    }
    await splash;
    ready = true;
    notifyListeners();
  }

  /// Reloads the shell data (counts, channels, to-dos).
  Future<void> refresh({bool silent = false}) async {
    try {
      boot = await api.get('/api/mobile/bootstrap');
      loadError = null;
      final actual = (workspace['id'] as num?)?.toInt();
      final mine = (me['id'] as num?)?.toInt();
      // The server falls back to the person's own workspace when access to another one ended.
      if (api.workspaceId != null && actual != api.workspaceId) {
        api.workspaceId = actual == mine ? null : actual;
        await _save('workspace', api.workspaceId?.toString());
      }
    } on ApiException catch (e) {
      loadError = e.message;
    }
    notifyListeners();
  }

  String get _platform => Platform.isIOS ? 'ios' : 'android';
  String get _device => Platform.isIOS ? 'iPhone app' : 'Android app';

  /// Returns a two-factor challenge when the account needs a code, else null (signed in).
  Future<String?> login(String email, String password) async {
    final res = await api.postJson('/api/mobile/login', {'email': email, 'password': password, 'device': _device, 'platform': _platform});
    if (res['status'] == 'two_factor') return res['challenge'] as String;
    await _signedIn(res['token'] as String);
    return null;
  }

  Future<void> confirmCode(String challenge, String code) async {
    final res = await api.postJson('/api/mobile/two-factor', {'challenge': challenge, 'code': code});
    await _signedIn(res['token'] as String);
  }

  Future<void> signup(String email, String password) async {
    final res = await api.postJson('/api/mobile/signup', {'email': email, 'password': password, 'device': _device, 'platform': _platform});
    await _signedIn(res['token'] as String);
  }

  Future<void> _signedIn(String token) async {
    api.token = token;
    api.workspaceId = null;
    await _save('token', token);
    await _save('workspace', null);
    await refresh();
  }

  /// Remembers a value on the device. If the phone's secure storage fails (it can when the disk is full),
  /// the app keeps working for this session and simply asks to log in again next time.
  Future<void> _save(String key, String? value) async {
    try {
      await _storage.write(key: key, value: value);
    } catch (e) {
      debugPrint('Could not save $key: $e');
    }
  }

  Future<void> logout() async {
    try {
      await api.postJson('/api/mobile/logout', {});
    } catch (_) {}
    await _signedOut();
  }

  /// Deletes the account for good (Account → Delete account). Throws an [ApiException] with the reason when
  /// the server refuses (wrong password, a paid plan that could not be cancelled).
  Future<String> deleteAccount(String password, String confirm) async {
    final res = await api.postForm('/app/account/delete', {'current': password, 'confirm': confirm});
    if (res['deleted'] != true) throw ApiException(422, (res['message'] ?? 'Nothing was deleted.').toString());
    await _signedOut();
    return (res['message'] ?? 'Your account was deleted.').toString();
  }

  Future<void> _signedOut() async {
    if (api.token == null) return;
    api.token = null;
    api.workspaceId = null;
    boot = null;
    try {
      await _storage.delete(key: 'token');
      await _storage.delete(key: 'workspace');
    } catch (_) {}
    notifyListeners();
  }

  Future<void> switchWorkspace(int id) async {
    final mine = (me['id'] as num?)?.toInt();
    api.workspaceId = id == mine ? null : id;
    await _save('workspace', api.workspaceId?.toString());
    await refresh();
  }

  Future<void> setTheme(ThemeMode mode) async {
    themeMode = mode;
    notifyListeners();
    await _save('theme', switch (mode) { ThemeMode.light => 'light', ThemeMode.dark => 'dark', _ => 'system' });
  }

  /// Turns the app's sound effects on or off (More → Sound effects).
  Future<void> setSound(bool on) async {
    sound = on;
    Fx.enabled = on;
    notifyListeners();
    await _save('sound', on ? 'on' : 'off');
  }
}

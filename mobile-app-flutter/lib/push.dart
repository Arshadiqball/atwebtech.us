import 'dart:io';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';

import 'config.dart';
import 'fx.dart';
import 'state.dart';

/// Push notifications through Firebase Cloud Messaging.
///
/// The Firebase values come from the server when the app starts (`push` in /api/mobile/bootstrap), so push
/// can be switched on for an app that is already in the stores. Values passed at build time (--dart-define,
/// see README.md) are used when the server sends none. With neither, this does nothing.
class Push {
  static bool _started = false;

  /// Where a tapped notification should take the person. Set by the app; called with the notification's
  /// `url` (for example /app/publish?tab=failed) or null.
  static void Function(String? url)? onOpen;

  static ({String apiKey, String projectId, String senderId, String appId})? _options(AppState app) {
    final server = app.boot?['push'];
    if (server is Map) {
      final appId = (Platform.isIOS ? server['ios_app_id'] : server['android_app_id'])?.toString() ?? '';
      final apiKey = (server['api_key'] ?? '').toString();
      final projectId = (server['project_id'] ?? '').toString();
      final senderId = (server['sender_id'] ?? '').toString();
      if (appId.isNotEmpty && apiKey.isNotEmpty && projectId.isNotEmpty && senderId.isNotEmpty) {
        return (apiKey: apiKey, projectId: projectId, senderId: senderId, appId: appId);
      }
    }
    final appId = Platform.isIOS ? Config.firebaseIosAppId : Config.firebaseAndroidAppId;
    if (Config.pushConfigured && appId.isNotEmpty) {
      return (apiKey: Config.firebaseApiKey, projectId: Config.firebaseProjectId, senderId: Config.firebaseSenderId, appId: appId);
    }
    return null;
  }

  static Future<void> start(AppState app, GlobalKey<ScaffoldMessengerState> messenger) async {
    if (_started || !app.signedIn) return;
    final options = _options(app);
    if (options == null) return;
    _started = true;
    try {
      if (Firebase.apps.isEmpty) {
        await Firebase.initializeApp(
          options: FirebaseOptions(apiKey: options.apiKey, appId: options.appId, messagingSenderId: options.senderId, projectId: options.projectId),
        );
      }
      final messaging = FirebaseMessaging.instance;
      final settings = await messaging.requestPermission();
      if (settings.authorizationStatus == AuthorizationStatus.denied) return;
      // iOS shows nothing while the app is open unless asked to. The app shows its own banner instead.
      await messaging.setForegroundNotificationPresentationOptions(alert: false, badge: true, sound: false);

      Future<void> register(String? token) async {
        if (token == null || !app.signedIn) return;
        try {
          await app.api.postJson('/api/mobile/device', {'push_token': token, 'platform': Platform.isIOS ? 'ios' : 'android'});
        } catch (_) {}
      }

      if (Platform.isIOS) await messaging.getAPNSToken();
      // The server (app/Push.php) sends to FCM registration tokens, so the token API is used on purpose.
      // ignore: deprecated_member_use
      await register(await messaging.getToken());
      // ignore: deprecated_member_use
      messaging.onTokenRefresh.listen(register);

      // App open: a banner with a sound, and fresh counts.
      FirebaseMessaging.onMessage.listen((message) {
        final n = message.notification;
        app.refresh();
        if (n == null) return;
        Fx.pop();
        final url = message.data['url']?.toString();
        messenger.currentState
          ?..hideCurrentSnackBar()
          ..showSnackBar(SnackBar(
            duration: const Duration(seconds: 6),
            content: Text([n.title, n.body].whereType<String>().where((t) => t.isNotEmpty).join('\n')),
            action: SnackBarAction(label: 'Open', onPressed: () => onOpen?.call(url)),
          ));
      });
      // Tapped from the lock screen or notification center, with the app in the background…
      FirebaseMessaging.onMessageOpenedApp.listen((message) {
        app.refresh();
        onOpen?.call(message.data['url']?.toString());
      });
      // …or with the app closed.
      final first = await messaging.getInitialMessage();
      if (first != null) onOpen?.call(first.data['url']?.toString());
    } catch (e) {
      debugPrint('Push setup failed: $e');
      _started = false;
    }
  }

  /// After signing out: the next person who signs in on this phone registers it for themselves.
  static void reset() => _started = false;
}

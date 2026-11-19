import 'dart:io';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';

import 'config.dart';
import 'state.dart';

/// Push notifications through Firebase Cloud Messaging. Does nothing until the Firebase values are
/// passed at build time (see README.md), so the app builds and runs without a Firebase project.
class Push {
  static bool _started = false;

  static Future<void> start(AppState app, GlobalKey<ScaffoldMessengerState> messenger, VoidCallback openNotifications) async {
    if (_started || !Config.pushConfigured || !app.signedIn) return;
    final appId = Platform.isIOS ? Config.firebaseIosAppId : Config.firebaseAndroidAppId;
    if (appId.isEmpty) return;
    _started = true;
    try {
      await Firebase.initializeApp(
        options: FirebaseOptions(apiKey: Config.firebaseApiKey, appId: appId, messagingSenderId: Config.firebaseSenderId, projectId: Config.firebaseProjectId),
      );
      final messaging = FirebaseMessaging.instance;
      final settings = await messaging.requestPermission();
      if (settings.authorizationStatus == AuthorizationStatus.denied) return;

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

      // App open: show a banner and refresh the counts.
      FirebaseMessaging.onMessage.listen((message) {
        final n = message.notification;
        app.refresh();
        if (n == null) return;
        messenger.currentState
          ?..hideCurrentSnackBar()
          ..showSnackBar(SnackBar(content: Text([n.title, n.body].whereType<String>().join('\n')), action: SnackBarAction(label: 'Open', onPressed: openNotifications)));
      });
      // Tapped from the lock screen or notification center.
      FirebaseMessaging.onMessageOpenedApp.listen((_) { app.refresh(); openNotifications(); });
      if (await messaging.getInitialMessage() != null) openNotifications();
    } catch (e) {
      debugPrint('Push setup failed: $e');
      _started = false;
    }
  }
}

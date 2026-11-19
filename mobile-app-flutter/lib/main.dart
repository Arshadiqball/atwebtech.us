import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'push.dart';
import 'screens/auth.dart';
import 'screens/more.dart';
import 'screens/notifications.dart';
import 'screens/shell.dart';
import 'screens/splash.dart';
import 'state.dart';
import 'theme.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const AtwebtechApp());
}

class AtwebtechApp extends StatefulWidget {
  const AtwebtechApp({super.key});

  @override
  State<AtwebtechApp> createState() => _AtwebtechAppState();
}

class _AtwebtechAppState extends State<AtwebtechApp> with WidgetsBindingObserver {
  final _app = AppState();
  final _nav = ShellNav();
  final _navigator = GlobalKey<NavigatorState>();
  final _messenger = GlobalKey<ScaffoldMessengerState>();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _app.addListener(_onState);
    Push.onOpen = _openFromPush;
    _app.start();
  }

  void _onState() {
    // Start push once someone is signed in.
    if (!_app.signedIn) {
      _nav.reset();
      Push.reset();
    }
    // The Firebase values arrive with the first load, so wait for it.
    if (_app.signedIn && _app.boot != null) Push.start(_app, _messenger);
  }

  /// A notification was tapped: go to the screen it is about.
  void _openFromPush(String? url) {
    final navigator = _navigator.currentState;
    if (navigator == null || !_app.signedIn) return;
    navigator.popUntil((route) => route.isFirst);
    final to = url ?? '';
    if (to.contains('tab=failed')) {
      _nav.openPublish('failed');
    } else if (to.contains('tab=approvals')) {
      _nav.openPublish('approvals');
    } else if (to.contains('tab=drafts')) {
      _nav.openPublish('drafts');
    } else if (to.startsWith('/app/publish')) {
      _nav.openPublish('queue');
    } else if (to.startsWith('/app/insights')) {
      _nav.go(3);
    } else if (to.startsWith('/app/community')) {
      _nav.go(2);
    } else if (to.startsWith('/app/channels')) {
      navigator.push(MaterialPageRoute(builder: (_) => const ChannelsScreen()));
    } else if (to == '/app') {
      _nav.go(0);
    } else {
      navigator.push(MaterialPageRoute(builder: (_) => const NotificationsScreen()));
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // Coming back from the browser (connecting a channel, paying) or from the background: reload.
    if (state == AppLifecycleState.resumed && _app.signedIn) _app.refresh();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _app.removeListener(_onState);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [ChangeNotifierProvider.value(value: _app), ChangeNotifierProvider.value(value: _nav)],
      child: Consumer<AppState>(
        builder: (context, app, _) => MaterialApp(
          title: 'atwebtech',
          debugShowCheckedModeBanner: false,
          navigatorKey: _navigator,
          scaffoldMessengerKey: _messenger,
          theme: buildTheme(Brightness.light),
          darkTheme: buildTheme(Brightness.dark),
          themeMode: app.themeMode,
          themeAnimationDuration: const Duration(milliseconds: 320),
          themeAnimationCurve: Curves.easeInOut,
          // Splash, sign-in and the app cross-fade into each other.
          home: AnimatedSwitcher(
            duration: const Duration(milliseconds: 380),
            switchInCurve: Curves.easeOut,
            child: !app.ready
                ? const SplashScreen(key: ValueKey('splash'))
                : (!app.signedIn
                    ? const AuthScreen(key: ValueKey('auth'))
                    : (app.boot == null ? _Offline(key: const ValueKey('offline'), message: app.loadError, onRetry: app.refresh, onLogout: app.logout) : const Shell(key: ValueKey('shell')))),
          ),
        ),
      ),
    );
  }
}

/// Signed in, but the first load failed (no connection, server down).
class _Offline extends StatelessWidget {
  const _Offline({super.key, required this.message, required this.onRetry, required this.onLogout});
  final String? message;
  final Future<void> Function() onRetry;
  final Future<void> Function() onLogout;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    return Scaffold(
      backgroundColor: c.bg,
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Icon(Icons.cloud_off_outlined, size: 44, color: c.muted),
            const SizedBox(height: 14),
            Text('Couldn’t reach atwebtech', style: TextStyle(fontSize: 19, fontWeight: FontWeight.w700, color: c.ink)),
            const SizedBox(height: 6),
            Text(message ?? 'Check your connection and try again.', textAlign: TextAlign.center, style: TextStyle(color: c.muted)),
            const SizedBox(height: 18),
            FilledButton(onPressed: onRetry, child: const Text('Try again')),
            TextButton(onPressed: onLogout, child: const Text('Log out')),
          ]),
        ),
      ),
    );
  }
}

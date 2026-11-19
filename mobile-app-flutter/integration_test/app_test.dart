// Walks through the app against a real server with a seeded test account:
//   flutter drive --driver=test_driver/integration_test.dart --target=integration_test/app_test.dart \
//     --dart-define=API_BASE=http://localhost:8000 --dart-define=TEST_EMAIL=… --dart-define=TEST_PASSWORD=…
import 'package:atwebtech/main.dart' as app;
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';

const email = String.fromEnvironment('TEST_EMAIL');
const password = String.fromEnvironment('TEST_PASSWORD');

Future<void> waitFor(WidgetTester tester, Finder finder, {int seconds = 20}) async {
  for (var i = 0; i < seconds * 5; i++) {
    await tester.pump(const Duration(milliseconds: 200));
    if (finder.evaluate().isNotEmpty) return;
  }
  // Say what was on screen instead, to make a failure easy to read.
  final seen = find.byType(Text).evaluate().map((e) => (e.widget as Text).data ?? '').where((t) => t.isNotEmpty).take(25).join(' | ');
  fail('Timed out waiting for $finder. On screen: $seen');
}

Future<void> rest(WidgetTester tester, [int ms = 900]) async {
  for (var i = 0; i < ms ~/ 100; i++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
}

void main() {
  final binding = IntegrationTestWidgetsFlutterBinding.ensureInitialized();

  testWidgets('sign in and walk through every screen', (tester) async {
    Future<void> shot(String name) async {
      await rest(tester);
      await binding.takeScreenshot(name);
    }

    // The on-screen keyboard covers buttons: close it before tapping below a text field.
    Future<void> hideKeyboard() async {
      FocusManager.instance.primaryFocus?.unfocus();
      await rest(tester, 700);
    }

    Future<void> back() async {
      await tester.pageBack();
      await rest(tester);
    }

    app.main();
    await waitFor(tester, find.byType(Scaffold));
    await rest(tester, 1500);

    // Sign in (skipped when the simulator still has a session from an earlier run).
    if (find.text('Log in to atwebtech').evaluate().isNotEmpty) {
      await shot('01-login');
      await tester.enterText(find.byType(TextField).at(0), email);
      // (A wrong password is covered by test/api_test.dart; repeating it here would trip the server's lockout.)
      await tester.enterText(find.byType(TextField).at(1), password);
      await hideKeyboard();
      await tester.tap(find.widgetWithText(FilledButton, 'Log in'));
    }

    // Home: what needs the person.
    await waitFor(tester, find.textContaining('Good '));
    expect(find.textContaining('failed to publish'), findsOneWidget);
    expect(find.text('Scheduled'), findsOneWidget);
    await shot('02-home');

    // Publish: queue, failed, drafts.
    await tester.tap(find.text('Publish').last);
    await waitFor(tester, find.textContaining('Share the three things'));
    await shot('03-queue');
    await tester.tap(find.text('Failed'));
    await waitFor(tester, find.text('Retry all'));
    expect(find.textContaining('needs a photo'), findsOneWidget);
    await shot('04-failed');

    // Composer: write a draft and save it.
    await tester.tap(find.text('New post'));
    await waitFor(tester, find.text('Post to'));
    await tester.tap(find.widgetWithText(FilterChip, 'atwebtech'));
    await tester.enterText(find.byType(TextField).first, 'Written by the app walkthrough test');
    await hideKeyboard();
    await shot('05-composer');
    await tester.tap(find.text('Save draft'));
    await waitFor(tester, find.text('Retry all'));
    await tester.ensureVisible(find.text('Drafts'));
    await rest(tester, 400);
    await tester.tap(find.text('Drafts'));
    await waitFor(tester, find.text('Written by the app walkthrough test'));
    await shot('06-drafts');

    // Community inbox.
    await tester.tap(find.text('Community').last);
    await waitFor(tester, find.text('@sara.designs'));
    expect(find.text('Reply'), findsWidgets);
    await shot('07-inbox');
    await tester.tap(find.text('Reply').first);
    await waitFor(tester, find.text('Send reply'));
    expect(find.text('Pricing'), findsOneWidget); // the saved reply
    await shot('08-reply');
    await hideKeyboard();
    await tester.tapAt(const Offset(200, 80));
    await rest(tester);

    // Insights.
    await tester.tap(find.text('Insights').last);
    await waitFor(tester, find.text('Posts published'));
    expect(find.text('Followers'), findsOneWidget);
    await shot('09-insights');

    // Notifications.
    await tester.tap(find.byTooltip('Notifications'));
    await waitFor(tester, find.text('Mark all read'));
    expect(find.textContaining('didn’t publish'), findsOneWidget);
    await shot('10-notifications');
    await back();

    // More and its screens.
    await tester.tap(find.text('More').last);
    await waitFor(tester, find.text('Media Library'));
    await shot('11-more');
    await tester.tap(find.text('Channels'));
    await waitFor(tester, find.text('Guided Hearts'));
    await shot('12-channels');
    await back();
    await tester.tap(find.text('Automations'));
    await waitFor(tester, find.textContaining('Weekly plan'));
    await shot('13-automations');
    await back();
    await tester.scrollUntilVisible(find.text('Plan & billing'), 200, scrollable: find.byType(Scrollable).last);
    await tester.tap(find.text('Account'));
    await waitFor(tester, find.text('Change password'));
    await shot('14-account');
    await back();
    await tester.tap(find.text('Plan & billing'));
    await waitFor(tester, find.text('Current plan'));
    await shot('15-plan');
    await back();

    // Dark theme.
    await tester.tap(find.text('Dark'));
    await rest(tester);
    await tester.tap(find.text('Home').last);
    await waitFor(tester, find.textContaining('Good '));
    await shot('16-home-dark');
    await tester.tap(find.text('Publish').last);
    await waitFor(tester, find.text('Queue'));
    await tester.tap(find.text('Failed'));
    await waitFor(tester, find.text('Retry all'));
    await shot('17-failed-dark');
    await tester.tap(find.text('More').last);
    await rest(tester);
    await tester.scrollUntilVisible(find.text('Auto'), 200, scrollable: find.byType(Scrollable).last);
    await tester.tap(find.text('Auto'));
    await rest(tester);
  });
}

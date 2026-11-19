// Uses the whole app against a real server with a seeded test account, the way a person would:
//   flutter drive --driver=test_driver/integration_test.dart --target=integration_test/app_test.dart \
//     --dart-define=API_BASE=http://localhost:8000 --dart-define=TEST_EMAIL=… --dart-define=TEST_PASSWORD=… \
//     --dart-define=NEW_EMAIL=… (an address with no account yet, to try signing up)
// It expects the demo content described in the README (posts in every tab, two open comments, a saved
// reply called "Pricing", one automation, a second workspace) and a server whose AI and networks are faked.
import 'package:atwebtech/main.dart' as app;
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';

const email = String.fromEnvironment('TEST_EMAIL');
const password = String.fromEnvironment('TEST_PASSWORD');
const newEmail = String.fromEnvironment('NEW_EMAIL');

String onScreen() => find.byType(Text).evaluate().map((e) => (e.widget as Text).data ?? '').where((t) => t.isNotEmpty).take(40).join(' | ');

Future<void> waitUntil(WidgetTester tester, bool Function() done, String what, {int seconds = 20}) async {
  for (var i = 0; i < seconds * 5; i++) {
    await tester.pump(const Duration(milliseconds: 200));
    if (done()) return;
  }
  // Say what was on screen instead, to make a failure easy to read.
  fail('Timed out waiting for $what. On screen: ${onScreen()}');
}

Future<void> waitFor(WidgetTester tester, Finder finder, {int seconds = 20}) => waitUntil(tester, () => finder.evaluate().isNotEmpty, '$finder', seconds: seconds);

Future<void> waitGone(WidgetTester tester, Finder finder, {int seconds = 20}) => waitUntil(tester, () => finder.evaluate().isEmpty, '$finder to go away', seconds: seconds);

Future<void> rest(WidgetTester tester, [int ms = 900]) async {
  for (var i = 0; i < ms ~/ 100; i++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
}

void main() {
  final binding = IntegrationTestWidgetsFlutterBinding.ensureInitialized();

  testWidgets('use every part of the app', (tester) async {
    var shots = 0;
    Future<void> shot(String name) async {
      await rest(tester, 1300);
      shots++;
      await binding.takeScreenshot('${shots.toString().padLeft(2, '0')}-$name');
    }

    // The on-screen keyboard covers buttons: close it before tapping below a text field.
    Future<void> hideKeyboard() async {
      FocusManager.instance.primaryFocus?.unfocus();
      binding.focusedEditable = null; // so typing into the same field again focuses it again
      await rest(tester, 700);
    }

    Future<void> tap(Finder finder, {int settle = 500}) async {
      await waitFor(tester, finder);
      await tester.tap(finder.first);
      await rest(tester, settle);
    }

    Future<void> back() async {
      await tester.pageBack();
      await rest(tester);
    }

    Future<void> goTab(String name) async {
      await tester.tap(find.descendant(of: find.byType(NavigationBar), matching: find.text(name)));
      await rest(tester, 600);
    }

    Future<void> publishTab(String name) async {
      final label = find.descendant(of: find.byType(TabBar), matching: find.text(name));
      await tester.ensureVisible(label);
      await rest(tester, 300);
      await tester.tap(label);
      await rest(tester, 700);
    }

    Future<void> logIn(String who, String pass) async {
      await waitFor(tester, find.text('Log in to atwebtech'));
      await tester.enterText(find.byType(TextField).at(0), who);
      await tester.enterText(find.byType(TextField).at(1), pass);
      await hideKeyboard();
      await tester.tap(find.widgetWithText(FilledButton, 'Log in'));
    }

    Future<void> logOut() async {
      await goTab('More');
      await tester.scrollUntilVisible(find.text('Log out'), 300, scrollable: find.byType(Scrollable).last);
      await tap(find.text('Log out'));
      await waitFor(tester, find.text('Log out?'));
      await tap(find.text('Log out').last);
      await waitFor(tester, find.text('Log in to atwebtech'));
    }

    app.main();
    await waitFor(tester, find.byType(Scaffold));
    await waitGone(tester, find.text('atwebtech.us')); // the splash
    await rest(tester, 1500);
    // A session left on the simulator by an earlier run: start from the sign-in screen.
    if (find.text('Log in to atwebtech').evaluate().isEmpty) {
      await waitFor(tester, find.byType(NavigationBar));
      await logOut();
    }

    // ---------- Sign-in screen
    await shot('login');
    await tap(find.widgetWithText(FilledButton, 'Log in'));
    await waitFor(tester, find.text('Enter your email and password.')); // nothing typed yet
    // Works whichever theme this phone was left in, and puts it back afterwards.
    final startedDark = find.byTooltip('Switch to light theme').evaluate().isNotEmpty;
    await tap(find.byTooltip(startedDark ? 'Switch to light theme' : 'Switch to dark theme'));
    await shot(startedDark ? 'login-light' : 'login-dark');
    await tap(find.byTooltip(startedDark ? 'Switch to dark theme' : 'Switch to light theme'));
    if (startedDark) await tap(find.byTooltip('Switch to light theme')); // the walkthrough itself runs in light
    await tap(find.text('Forgot your password?'));
    await tester.enterText(find.byType(TextField).last, email);
    await tap(find.text('Send link'));
    // This test server sends no email, and says so. (With email on, it confirms the link is on its way.)
    await waitUntil(tester, () => find.textContaining('reset').evaluate().isNotEmpty, 'the answer about the reset email');
    await rest(tester, 600);

    // ---------- Create an account, look around the empty app, log out
    if (newEmail.isNotEmpty) {
      await tap(find.text('Create an account'));
      await waitFor(tester, find.text('Create your atwebtech account'));
      await tester.enterText(find.byType(TextField).at(0), newEmail);
      await tester.enterText(find.byType(TextField).at(1), 'short');
      await hideKeyboard();
      await tap(find.widgetWithText(FilledButton, 'Create account'));
      await waitFor(tester, find.textContaining('at least 8 characters.')); // the server refused the weak password
      await rest(tester, 800);
      await tester.enterText(find.byType(TextField).at(1), 'WalkPass#123');
      await rest(tester, 300);
      expect(tester.widget<TextField>(find.byType(TextField).at(1)).controller!.text, 'WalkPass#123');
      await hideKeyboard();
      await shot('signup');
      await tap(find.widgetWithText(FilledButton, 'Create account'));
      await waitFor(tester, find.text('Connect your first channel'));
      await shot('home-new-account');
      await goTab('Publish');
      await waitFor(tester, find.text('Your queue is empty'));
      await goTab('Community');
      await waitFor(tester, find.text('Answer every comment from one inbox'));
      // Delete this new account from inside the app (wrong password first).
      await goTab('More');
      await tester.scrollUntilVisible(find.text('Account'), 200, scrollable: find.byType(Scrollable).last);
      await tap(find.text('Account'));
      await waitFor(tester, find.text('Change password'));
      await tester.dragUntilVisible(find.text('Delete my account'), find.byType(ListView).last, const Offset(0, -300));
      await rest(tester, 500);
      await tap(find.text('Delete my account'));
      await tester.enterText(find.byType(TextField).at(find.byType(TextField).evaluate().length - 2), 'not-it');
      await tester.enterText(find.byType(TextField).last, 'DELETE');
      await tap(find.text('Delete account'));
      await waitFor(tester, find.textContaining('password isn’t right'));
      await hideKeyboard();
      await waitGone(tester, find.textContaining('password isn’t right')); // the message sits over the button
      await rest(tester, 600);
      await tap(find.text('Delete my account'));
      await tester.enterText(find.byType(TextField).at(find.byType(TextField).evaluate().length - 2), 'WalkPass#123');
      await tester.enterText(find.byType(TextField).last, 'DELETE');
      await tap(find.text('Delete account'));
      await waitFor(tester, find.text('Log in to atwebtech'));
      await rest(tester, 3200);
    }

    // ---------- Log in
    await logIn(email, password);
    await waitFor(tester, find.textContaining('Good '));
    expect(find.textContaining('failed to publish'), findsOneWidget);
    expect(find.text('Scheduled'), findsOneWidget);
    await shot('home');

    // ---------- Home shortcuts
    await tester.scrollUntilVisible(find.text('See what’s working'), 200, scrollable: find.byType(Scrollable).last);
    await tap(find.text('See what’s working'));
    await waitFor(tester, find.text('Posts published'));
    await goTab('Home');
    await tester.scrollUntilVisible(find.textContaining('failed to publish'), -200, scrollable: find.byType(Scrollable).last);
    await tap(find.textContaining('failed to publish'));
    await waitFor(tester, find.text('Retry all'));
    expect(find.textContaining('needs a photo'), findsOneWidget);
    await shot('failed');

    // ---------- Publish: edit and delete a scheduled post
    await publishTab('Queue');
    await waitFor(tester, find.textContaining('Share the three things'));
    await shot('queue');
    await tap(find.text('Edit'));
    await waitFor(tester, find.text('Edit post'));
    await waitFor(tester, find.text('Post to'));
    await tester.enterText(find.byType(TextField).first, 'Edited by the walkthrough');
    await hideKeyboard();
    await tap(find.widgetWithText(FilledButton, 'Schedule'));
    await waitFor(tester, find.text('Edited by the walkthrough'));
    await rest(tester, 1500);
    await tap(find.byTooltip('Delete'));
    await tap(find.widgetWithText(TextButton, 'Delete'));
    await waitGone(tester, find.text('Edited by the walkthrough'));

    // ---------- Composer: AI writer, repeat, date and time, schedule
    await tap(find.text('New post'));
    await waitFor(tester, find.text('Post to'));
    await tap(find.widgetWithText(FilledButton, 'Schedule'));
    await waitFor(tester, find.text('Choose at least one channel.'));
    await tap(find.widgetWithText(FilterChip, 'atwebtech'));
    await tap(find.text('AI'));
    await tester.enterText(find.byType(TextField).last, 'our new autumn menu, friendly');
    await tap(find.text('Write it'));
    await waitFor(tester, find.textContaining('Fresh autumn menu'));
    await hideKeyboard();
    await tester.scrollUntilVisible(find.text('Repeat'), 200, scrollable: find.byType(Scrollable).first);
    await tap(find.byType(DropdownButton<String>));
    await tap(find.text('Every week').last);
    await tap(find.text('When'));
    await tap(find.text('OK'), settle: 1200); // the date
    await tap(find.text('OK'), settle: 1200); // the time
    await shot('composer');
    await tap(find.widgetWithText(FilledButton, 'Schedule'));
    await waitGone(tester, find.text('Post to'));
    await waitFor(tester, find.textContaining('Fresh autumn menu'));
    await waitFor(tester, find.text('Repeats'));
    await shot('queue-after-scheduling');

    // ---------- Drafts: save one, duplicate one
    await tap(find.text('New post'));
    await waitFor(tester, find.text('Post to'));
    await tap(find.widgetWithText(FilterChip, 'atwebtech'));
    await tester.enterText(find.byType(TextField).first, 'Draft written by the walkthrough');
    await hideKeyboard();
    await tap(find.text('Save draft'));
    await waitFor(tester, find.text('Draft written by the walkthrough'));
    await shot('drafts');
    await tap(find.text('Duplicate'));
    await waitFor(tester, find.text('Edit post'));
    await waitFor(tester, find.text('Post to'));
    await tap(find.byTooltip('Close'));

    // ---------- Approvals: send one, ask for changes, approve
    await tap(find.text('New post'));
    await waitFor(tester, find.text('Post to'));
    await tap(find.widgetWithText(FilterChip, 'atwebtech'));
    await tester.enterText(find.byType(TextField).first, 'Please check this one');
    await hideKeyboard();
    await tap(find.byTooltip('Send for approval'));
    await waitFor(tester, find.text('Please check this one'));
    await shot('approvals');
    await tap(find.text('Ask for changes'));
    await tester.enterText(find.byType(TextField).last, 'Shorter please');
    await tap(find.text('Send back'));
    await waitUntil(tester, () => find.text('Ask for changes').evaluate().length == 1, 'one post left to approve');
    await tap(find.text('Approve'));
    await waitFor(tester, find.text('Nothing waiting for approval'));
    await publishTab('Drafts');
    await waitFor(tester, find.text('Note: Shorter please'));

    // ---------- Failed: retry one, delete the rest
    await publishTab('Failed');
    await waitFor(tester, find.text('Retry all'));
    await waitFor(tester, find.byKey(const ValueKey('count-failed-3')));
    await tap(find.text('Retry'));
    await waitFor(tester, find.byKey(const ValueKey('count-failed-2'))); // the retried post left Failed
    await tap(find.text('Delete all'));
    await tap(find.widgetWithText(TextButton, 'Delete'));
    await waitFor(tester, find.text('No failed posts'));

    // ---------- Sent: recycle a post
    await publishTab('Sent');
    await waitFor(tester, find.textContaining('Published '));
    // The newest sent post is evergreen: turn that off, then on again.
    final evergreen = find.text('Evergreen').evaluate().length;
    await tap(find.text('Evergreen'));
    await waitUntil(tester, () => find.text('Evergreen').evaluate().length == evergreen - 1, 'the post to stop recycling');
    await tap(find.text('Recycle'));
    await waitUntil(tester, () => find.text('Evergreen').evaluate().length == evergreen, 'the post to become evergreen again');
    await shot('sent');

    // ---------- Community: AI suggestion, saved reply, send, mark done, filters
    await goTab('Community');
    await waitFor(tester, find.text('@sara.designs'));
    await shot('inbox');
    await tap(find.text('Reply'));
    await waitFor(tester, find.text('Send reply'));
    await tap(find.text('Suggest a reply'));
    await waitFor(tester, find.textContaining('Fresh autumn menu'));
    await tap(find.text('Pricing')); // the saved reply replaces it
    await waitFor(tester, find.textContaining('Thanks for asking'));
    await shot('reply');
    await hideKeyboard();
    await tap(find.text('Send reply'));
    await waitGone(tester, find.text('@sara.designs'));
    await tap(find.text('Mark done'));
    await waitFor(tester, find.text('You’re all caught up'));
    await tap(find.textContaining('Done '));
    await waitFor(tester, find.text('Move to Unanswered'));
    await tap(find.text('Move to Unanswered'));
    await waitFor(tester, find.text('Nothing here'));
    await tap(find.textContaining('Answered '));
    await waitFor(tester, find.textContaining('You replied: Thanks for asking'));
    await tester.ensureVisible(find.text('Check now'));
    await rest(tester, 400);
    await tap(find.text('Check now'));
    await waitFor(tester, find.byIcon(Icons.check_circle_outline)); // the server answered
    await tester.ensureVisible(find.textContaining('Unanswered '));
    await rest(tester, 400);
    await tap(find.textContaining('Unanswered '));
    await waitFor(tester, find.text('@lena.bsky.social'));

    // ---------- Insights
    await goTab('Insights');
    await waitFor(tester, find.text('Posts published'));
    expect(find.text('Followers'), findsOneWidget);
    await shot('insights');
    await tap(find.text('7 days'), settle: 1500);
    await waitFor(tester, find.text('Posts published'));
    await tap(find.text('Last month'), settle: 1500);
    await waitFor(tester, find.text('Posts published'));

    // ---------- Notifications: open one, mark the rest read
    await tap(find.byTooltip('Notifications'));
    await waitFor(tester, find.text('Mark all read'));
    expect(find.textContaining('didn’t publish'), findsOneWidget);
    await shot('notifications');
    await tap(find.text('Mia sent a post for approval'));
    await waitFor(tester, find.text('Nothing waiting for approval')); // it opened Publish → Approvals
    await tap(find.byTooltip('Notifications'));
    await tap(find.text('Mark all read'), settle: 1500);
    await waitGone(tester, find.textContaining(' · new'));
    await back();

    // ---------- More: channels, automations, library, account, plan
    await goTab('More');
    await waitFor(tester, find.text('Media Library'));
    await shot('more');
    await tap(find.text('Channels'));
    await waitFor(tester, find.text('Guided Hearts'));
    await shot('channels');
    await tap(find.text('Pause queue'));
    await waitFor(tester, find.text('Resume queue'));
    await tap(find.text('Resume queue'));
    await waitGone(tester, find.text('Resume queue'));
    await back();

    await tap(find.text('Automations'));
    await waitFor(tester, find.textContaining('Weekly plan'));
    await shot('automations');
    expect(tester.widget<Switch>(find.byType(Switch).first).value, isTrue);
    await tap(find.byType(Switch));
    await waitUntil(tester, () => tester.widget<Switch>(find.byType(Switch).first).value == false, 'the automation to pause');
    await tap(find.byType(Switch));
    await waitUntil(tester, () => tester.widget<Switch>(find.byType(Switch).first).value == true, 'the automation to resume');
    await back();

    await tap(find.text('Media Library'));
    await waitFor(tester, find.text('Your library is empty'));
    await back();

    await tester.scrollUntilVisible(find.text('Plan & billing'), 200, scrollable: find.byType(Scrollable).last);
    await tap(find.text('Account'));
    await waitFor(tester, find.text('Change password'));
    await shot('account');
    await tester.enterText(find.byType(TextField).at(0), 'Walk Tester');
    await hideKeyboard();
    await tap(find.widgetWithText(FilledButton, 'Save'));
    await waitFor(tester, find.text('Name saved.'));
    await tester.enterText(find.byType(TextField).at(1), 'not-my-password');
    await tester.enterText(find.byType(TextField).at(2), 'NewPass#12345');
    await hideKeyboard();
    await tap(find.text('Change password'));
    await waitFor(tester, find.textContaining('current password isn’t right'));
    final alert = find.byType(SwitchListTile).first;
    await tester.scrollUntilVisible(alert, 200, scrollable: find.byType(Scrollable).last);
    final wasOn = tester.widget<SwitchListTile>(alert).value;
    await tap(alert, settle: 1200);
    expect(tester.widget<SwitchListTile>(alert).value, !wasOn);
    await tap(alert, settle: 1200);
    await back();
    await tester.scrollUntilVisible(find.text('Walk Tester'), -300, scrollable: find.byType(Scrollable).last);

    await tester.scrollUntilVisible(find.text('Plan & billing'), 200, scrollable: find.byType(Scrollable).last);
    await tap(find.text('Plan & billing'));
    await waitFor(tester, find.text('Current plan'));
    await shot('plan');
    await back();

    // ---------- Switch workspace and back
    if (find.text('Client Bakery').evaluate().isEmpty) {
      await tester.scrollUntilVisible(find.text('Client Bakery'), -200, scrollable: find.byType(Scrollable).last);
    }
    await tap(find.text('Client Bakery'), settle: 1500);
    await goTab('Home');
    await waitFor(tester, find.text('Connect your first channel'));
    expect(find.textContaining('Client Bakery'), findsWidgets);
    await goTab('More');
    await tap(find.text('My workspace'), settle: 1500);
    await goTab('Home');
    await waitFor(tester, find.text('QUICK ACTIONS'));
    expect(find.text('Connect your first channel'), findsNothing);

    // ---------- Sound switch, then the dark theme on every tab
    await goTab('More');
    final sound = find.descendant(of: find.widgetWithText(ListTile, 'Sound effects'), matching: find.byType(Switch));
    await tester.scrollUntilVisible(sound, 200, scrollable: find.byType(Scrollable).last);
    await tap(sound);
    expect(tester.widget<Switch>(sound).value, isFalse);
    await tap(sound);
    expect(tester.widget<Switch>(sound).value, isTrue);
    await tap(find.text('Dark'));
    await shot('more-dark');
    await goTab('Home');
    await waitFor(tester, find.textContaining('Good '));
    await shot('home-dark');
    await goTab('Publish');
    await publishTab('Queue');
    await waitFor(tester, find.textContaining('Fresh autumn menu'));
    await shot('queue-dark');
    await tap(find.text('New post'));
    await waitFor(tester, find.text('Post to'));
    await shot('composer-dark');
    await tap(find.byTooltip('Close'));
    await goTab('Community');
    await waitFor(tester, find.text('@lena.bsky.social'));
    await shot('inbox-dark');
    await goTab('Insights');
    await waitFor(tester, find.text('Posts published'));
    await shot('insights-dark');
    await tap(find.byTooltip('Switch to light theme'));
    await goTab('More');
    await tester.scrollUntilVisible(find.text('Auto'), 200, scrollable: find.byType(Scrollable).last);
    await tap(find.text(startedDark ? 'Dark' : 'Auto'));

    // ---------- Log out
    await logOut();
    await shot('logged-out');
  });
}

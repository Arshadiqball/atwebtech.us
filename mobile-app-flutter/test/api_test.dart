// Exercises the app's API client against a real server with a seeded test account:
//   flutter test --dart-define=API_BASE=http://localhost:8000 --dart-define=TEST_EMAIL=… --dart-define=TEST_PASSWORD=…
// Skipped when no test account is given.
import 'dart:io';

import 'package:atwebtech/api.dart';
import 'package:flutter_test/flutter_test.dart';

const email = String.fromEnvironment('TEST_EMAIL');
const password = String.fromEnvironment('TEST_PASSWORD');

void main() {
  final skip = email.isEmpty ? 'Pass TEST_EMAIL and TEST_PASSWORD to run' : null;
  final api = Api();

  test('rejects a wrong password with the server message', () async {
    expect(() => api.postJson('/api/mobile/login', {'email': email, 'password': 'nope'}), throwsA(isA<ApiException>().having((e) => e.message, 'message', contains('incorrect'))));
  }, skip: skip);

  test('signs in, loads the shell data and each screen', () async {
    final res = await api.postJson('/api/mobile/login', {'email': email, 'password': password, 'device': 'dart test', 'platform': 'ios'});
    api.token = res['token'] as String;
    final boot = await api.get('/api/mobile/bootstrap');
    expect(boot['me']['email'], email);
    expect(boot['channels'], isNotEmpty);
    for (final path in ['/api/mobile/posts?tab=queue', '/api/mobile/inbox', '/api/mobile/notifications', '/api/mobile/insights', '/api/mobile/automations', '/api/mobile/library', '/api/mobile/account']) {
      expect((await api.get(path))['status'], 'ok', reason: path);
    }
  }, skip: skip);

  test('creates a draft through the website handler, with field errors as ApiException', () async {
    final boot = await api.get('/api/mobile/bootstrap');
    final channel = (boot['channels'] as List).firstWhere((c) => c['network'] == 'threads');
    await expectLater(api.postForm('/app/posts', {'action': 'draft', 'targets': ['c:${channel['id']}'], 'body': ''}), throwsA(isA<ApiException>().having((e) => e.detail, 'detail', contains('Write something'))));
    final ok = await api.postForm('/app/posts', {'action': 'draft', 'targets': ['c:${channel['id']}'], 'body': 'Draft from the Dart API test'});
    expect(ok['message'], contains('draft'));
    final drafts = await api.get('/api/mobile/posts', {'tab': 'drafts'});
    final mine = (drafts['posts'] as List).firstWhere((p) => p['text'] == 'Draft from the Dart API test');
    await api.postForm('/app/posts', {'action': 'delete', 'id': mine['id']});
  }, skip: skip);

  test('uploads a photo in chunks and deletes it', () async {
    // A tiny valid 1x1 PNG padded with an ancillary chunk would be over-engineering: use the app logo.
    final file = File('assets/logo.png');
    var last = 0.0;
    final media = await api.upload(file, 'logo.png', (p) => last = p);
    expect(media['kind'], 'image');
    expect(last, 1.0);
    final library = await api.get('/api/mobile/library');
    expect((library['items'] as List).any((m) => m['id'] == media['id']), isTrue);
    await api.postForm('/app/library', {'action': 'delete', 'id': media['id']});
  }, skip: skip);

  test('signs out: the token stops working', () async {
    await api.postJson('/api/mobile/logout', {});
    expect(() => api.get('/api/mobile/bootstrap'), throwsA(isA<ApiException>().having((e) => e.status, 'status', 401)));
  }, skip: skip);
}

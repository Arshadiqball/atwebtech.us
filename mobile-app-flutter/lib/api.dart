import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;
import 'package:url_launcher/url_launcher.dart';

import 'config.dart';

/// An error the server (or the network) reported, with a message that can be shown to the person.
class ApiException implements Exception {
  ApiException(this.status, this.message, [this.errors = const {}]);
  final int status;
  final String message;
  final Map<String, dynamic> errors;

  /// The first field error when there is one, else the general message.
  String get detail => errors.values.isNotEmpty ? errors.values.first.toString() : message;

  @override
  String toString() => detail;
}

/// Talks to the atwebtech server. Every request carries the device token, so the server treats it
/// exactly like the signed-in website (same handlers, same permissions).
class Api {
  String? token;
  int? workspaceId;
  void Function()? onUnauthorized;
  final _client = http.Client();

  Map<String, String> get authHeaders => {
        'Accept': 'application/json',
        if (token != null) 'Authorization': 'Bearer $token',
        if (workspaceId != null) 'X-Workspace': '$workspaceId',
      };

  Uri uri(String path, [Map<String, String>? query]) {
    final base = Uri.parse(Config.apiBase);
    final parsed = Uri.parse(path);
    return base.replace(path: parsed.path, queryParameters: {...parsed.queryParameters, ...?query}.isEmpty ? null : {...parsed.queryParameters, ...?query});
  }

  /// Address of a private file (uploaded media). Load it with [authHeaders].
  String fileUrl(String path) => uri(path).toString();

  Future<Map<String, dynamic>> get(String path, [Map<String, String>? query]) async {
    return _send(() => _client.get(uri(path, query), headers: authHeaders));
  }

  Future<Map<String, dynamic>> postJson(String path, Map<String, dynamic> body) async {
    return _send(() => _client.post(uri(path), headers: {...authHeaders, 'Content-Type': 'application/json'}, body: jsonEncode(body)));
  }

  /// Posts like an HTML form. A list value is sent as repeated `name[]` fields.
  Future<Map<String, dynamic>> postForm(String path, Map<String, dynamic> fields) async {
    final parts = <String>[];
    fields.forEach((key, value) {
      if (value == null) return;
      if (value is Iterable) {
        for (final v in value) {
          parts.add('${Uri.encodeQueryComponent('$key[]')}=${Uri.encodeQueryComponent('$v')}');
        }
      } else {
        parts.add('${Uri.encodeQueryComponent(key)}=${Uri.encodeQueryComponent('$value')}');
      }
    });
    return _send(() => _client.post(uri(path), headers: {...authHeaders, 'Content-Type': 'application/x-www-form-urlencoded'}, body: parts.join('&')));
  }

  Future<Map<String, dynamic>> _send(Future<http.Response> Function() request) async {
    http.Response res;
    try {
      res = await request().timeout(const Duration(seconds: 40));
    } on SocketException {
      throw ApiException(0, 'No connection. Check your internet and try again.');
    } on Exception {
      throw ApiException(0, 'The server didn’t answer. Please try again.');
    }
    Map<String, dynamic> data = {};
    try {
      final decoded = jsonDecode(utf8.decode(res.bodyBytes));
      if (decoded is Map<String, dynamic>) data = decoded;
    } catch (_) {}
    if (res.statusCode == 401 && token != null && data['code'] == 'unauthorized') {
      onUnauthorized?.call();
    }
    final failed = res.statusCode >= 400 || data['status'] == 'error';
    if (failed) {
      final errors = data['errors'] is Map ? Map<String, dynamic>.from(data['errors'] as Map) : <String, dynamic>{};
      throw ApiException(res.statusCode, (data['message'] ?? 'Something went wrong. Please try again.').toString(), errors);
    }
    return data;
  }

  static const _types = {
    'jpg': 'image/jpeg', 'jpeg': 'image/jpeg', 'png': 'image/png', 'webp': 'image/webp', 'gif': 'image/gif',
    'mp4': 'video/mp4', 'mov': 'video/quicktime', 'webm': 'video/webm',
  };

  /// Uploads a photo or video in chunks (the same protocol the website uses) and returns the media record.
  Future<Map<String, dynamic>> upload(File file, String name, void Function(double progress) onProgress) async {
    final ext = name.contains('.') ? name.split('.').last.toLowerCase() : '';
    final type = _types[ext];
    if (type == null) {
      throw ApiException(415, 'Use a JPG, PNG, WebP or GIF image, or an MP4, MOV or WebM video.');
    }
    final size = await file.length();
    final opened = await postForm('/app/media', {'name': name, 'size': size, 'type': type});
    final id = opened['id'];
    final chunk = (opened['chunk'] as num).toInt();
    final reader = await file.open();
    try {
      var offset = 0;
      while (offset < size) {
        final bytes = await reader.read(chunk);
        final sent = await _send(() => _client.post(uri('/app/media/$id/chunk'),
            headers: {...authHeaders, 'Content-Type': 'application/octet-stream', 'X-Upload-Offset': '$offset'}, body: bytes));
        offset = (sent['offset'] as num).toInt();
        onProgress(offset / size);
      }
    } finally {
      await reader.close();
    }
    final done = await postForm('/app/media/$id/complete', {});
    return Map<String, dynamic>.from(done['media'] as Map);
  }

  /// Opens a page of the website already signed in (connecting channels, billing, team, …).
  /// [external] uses the phone's browser instead of the in-app browser; payments must use that.
  Future<void> openWeb(String next, {bool external = false}) async {
    final res = await postJson('/api/mobile/handoff', {'next': next});
    final url = Uri.parse(res['url'] as String);
    final ok = await launchUrl(url, mode: external ? LaunchMode.externalApplication : LaunchMode.inAppBrowserView);
    if (!ok) throw ApiException(0, 'Couldn’t open the browser.');
  }

  Future<void> openLink(String url) async {
    final parsed = Uri.tryParse(url);
    if (parsed == null || !(parsed.scheme == 'https' || parsed.scheme == 'http')) return;
    await launchUrl(parsed, mode: LaunchMode.externalApplication);
  }
}

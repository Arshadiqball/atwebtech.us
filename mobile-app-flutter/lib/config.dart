/// Build-time settings, passed with --dart-define (see README.md).
class Config {
  /// The atwebtech server the app talks to.
  static const apiBase = String.fromEnvironment('API_BASE', defaultValue: 'https://atwebtech.us');

  // Firebase Cloud Messaging (push). Leave empty to build without push.
  static const firebaseApiKey = String.fromEnvironment('FIREBASE_API_KEY');
  static const firebaseProjectId = String.fromEnvironment('FIREBASE_PROJECT_ID');
  static const firebaseSenderId = String.fromEnvironment('FIREBASE_SENDER_ID');
  static const firebaseAndroidAppId = String.fromEnvironment('FIREBASE_ANDROID_APP_ID');
  static const firebaseIosAppId = String.fromEnvironment('FIREBASE_IOS_APP_ID');

  static bool get pushConfigured => firebaseApiKey.isNotEmpty && firebaseProjectId.isNotEmpty && firebaseSenderId.isNotEmpty;
}

import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

/// The app's short interface sounds (assets/sounds/*.wav).
enum Sfx { tap, toggle, success, send, error, pop, welcome }

/// Sound effects and matching haptics. Sounds can be turned off under More → Sound effects;
/// they also stay quiet when the phone is on silent, and never interrupt music that is playing.
class Fx {
  Fx._();

  static bool enabled = true;
  static bool _configured = false;
  static final Map<Sfx, AudioPlayer> _players = {};

  static const _volume = {Sfx.tap: 0.35, Sfx.toggle: 0.45, Sfx.success: 0.6, Sfx.send: 0.6, Sfx.error: 0.55, Sfx.pop: 0.45, Sfx.welcome: 0.6};

  static Future<void> _configure() async {
    if (_configured) return;
    _configured = true;
    try {
      // Interface sounds: follow the silent switch on iPhone and leave any music playing on both platforms.
      await AudioPlayer.global.setAudioContext(AudioContext(
        iOS: AudioContextIOS(category: AVAudioSessionCategory.ambient),
        android: const AudioContextAndroid(contentType: AndroidContentType.sonification, usageType: AndroidUsageType.assistanceSonification, audioFocus: AndroidAudioFocus.none),
      ));
    } catch (e) {
      debugPrint('Sound setup: $e');
    }
  }

  /// Plays a sound (when sounds are on) with its haptic. Never throws: a missing speaker must not break a tap.
  static void play(Sfx sound) {
    _haptic(sound);
    if (!enabled) return;
    () async {
      try {
        await _configure();
        final player = _players[sound] ??= AudioPlayer(playerId: 'fx-${sound.name}');
        await player.stop();
        await player.play(AssetSource('sounds/${sound.name}.wav'), volume: _volume[sound] ?? 0.5);
      } catch (e) {
        debugPrint('Sound ${sound.name}: $e');
      }
    }();
  }

  static void _haptic(Sfx sound) {
    switch (sound) {
      case Sfx.tap || Sfx.toggle || Sfx.pop:
        HapticFeedback.selectionClick();
      case Sfx.success || Sfx.send || Sfx.welcome:
        HapticFeedback.lightImpact();
      case Sfx.error:
        HapticFeedback.mediumImpact();
    }
  }

  static void tap() => play(Sfx.tap);
  static void toggle() => play(Sfx.toggle);
  static void success() => play(Sfx.success);
  static void send() => play(Sfx.send);
  static void error() => play(Sfx.error);
  static void pop() => play(Sfx.pop);
}

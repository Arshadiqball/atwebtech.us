import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api.dart';
import '../state.dart';
import '../theme.dart';
import '../widgets.dart';

/// Log in or create an account. There is no marketing site in the app: this is the first screen.
class AuthScreen extends StatefulWidget {
  const AuthScreen({super.key});

  @override
  State<AuthScreen> createState() => _AuthScreenState();
}

class _AuthScreenState extends State<AuthScreen> {
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _signup = false;
  bool _busy = false;
  bool _show = false;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  bool get _long => _password.text.length >= 8;
  bool get _upper => _password.text.contains(RegExp(r'[A-Z]'));
  bool get _symbol => _password.text.contains(RegExp(r'[0-9\W_]'));

  Future<void> _submit() async {
    final app = context.read<AppState>();
    final email = _email.text.trim();
    if (email.isEmpty || _password.text.isEmpty) {
      setState(() => _error = 'Enter your email and password.');
      return;
    }
    setState(() { _busy = true; _error = null; });
    try {
      if (_signup) {
        await app.signup(email, _password.text);
      } else {
        final challenge = await app.login(email, _password.text);
        if (challenge != null && mounted) {
          await Navigator.of(context).push(MaterialPageRoute(builder: (_) => TwoFactorScreen(challenge: challenge)));
        }
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.detail);
    } catch (_) {
      if (mounted) setState(() => _error = 'Something went wrong on this phone. Please try again.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _forgot() async {
    final app = context.read<AppState>();
    final controller = TextEditingController(text: _email.text.trim());
    final email = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Reset your password'),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          const Text('Enter your email and we’ll send a link to choose a new password.'),
          const SizedBox(height: 14),
          TextField(controller: controller, keyboardType: TextInputType.emailAddress, autocorrect: false, decoration: const InputDecoration(hintText: 'Your email address')),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.pop(ctx, controller.text.trim()), child: const Text('Send link')),
        ],
      ),
    );
    if (email == null || email.isEmpty || !mounted) return;
    try {
      final res = await app.api.postJson('/api/mobile/forgot', {'email': email});
      if (mounted) showSnack(context, res['message']?.toString());
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    return Scaffold(
      backgroundColor: c.bg,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                Center(child: Image.asset('assets/logo.png', width: 64, height: 64)),
                const SizedBox(height: 18),
                Text(_signup ? 'Create your atwebtech account' : 'Log in to atwebtech', textAlign: TextAlign.center, style: TextStyle(fontSize: 24, fontWeight: FontWeight.w800, letterSpacing: -0.5, color: c.ink)),
                const SizedBox(height: 6),
                Text(_signup ? 'Free for up to 3 channels. No credit card.' : 'Plan, schedule and publish from your phone.', textAlign: TextAlign.center, style: TextStyle(color: c.muted)),
                const SizedBox(height: 26),
                if (_error != null) ...[Callout(_error!), const SizedBox(height: 14)],
                TextField(
                  controller: _email, keyboardType: TextInputType.emailAddress, autocorrect: false, textInputAction: TextInputAction.next,
                  autofillHints: [_signup ? AutofillHints.newUsername : AutofillHints.username],
                  decoration: const InputDecoration(labelText: 'Email'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _password, obscureText: !_show, textInputAction: TextInputAction.done, onSubmitted: (_) => _submit(),
                  onChanged: (_) { if (_signup) setState(() {}); },
                  autofillHints: [_signup ? AutofillHints.newPassword : AutofillHints.password],
                  decoration: InputDecoration(
                    labelText: _signup ? 'Create password' : 'Password',
                    suffixIcon: IconButton(icon: Icon(_show ? Icons.visibility_off_outlined : Icons.visibility_outlined), onPressed: () => setState(() => _show = !_show), tooltip: _show ? 'Hide password' : 'Show password'),
                  ),
                ),
                if (_signup) ...[
                  const SizedBox(height: 10),
                  _Rule('At least 8 characters', _long),
                  _Rule('1 uppercase letter', _upper),
                  _Rule('1 number or symbol', _symbol),
                ],
                const SizedBox(height: 18),
                FilledButton(
                  onPressed: _busy ? null : _submit,
                  child: _busy ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2.4)) : Text(_signup ? 'Create account' : 'Log in'),
                ),
                const SizedBox(height: 10),
                if (!_signup) TextButton(onPressed: _forgot, child: const Text('Forgot your password?')),
                const SizedBox(height: 6),
                Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                  Text(_signup ? 'Already have an account?' : 'New to atwebtech?', style: TextStyle(color: c.ink2)),
                  TextButton(onPressed: () => setState(() { _signup = !_signup; _error = null; }), child: Text(_signup ? 'Log in' : 'Create an account')),
                ]),
                if (_signup)
                  Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Wrap(alignment: WrapAlignment.center, crossAxisAlignment: WrapCrossAlignment.center, children: [
                      Text('By signing up you agree to the ', style: TextStyle(color: c.muted, fontSize: 12.5)),
                      InkWell(onTap: () => context.read<AppState>().api.openLink('https://atwebtech.us/terms'), child: Text('terms', style: TextStyle(color: c.greenInk, fontSize: 12.5, fontWeight: FontWeight.w600))),
                      Text(' and ', style: TextStyle(color: c.muted, fontSize: 12.5)),
                      InkWell(onTap: () => context.read<AppState>().api.openLink('https://atwebtech.us/privacy'), child: Text('privacy policy', style: TextStyle(color: c.greenInk, fontSize: 12.5, fontWeight: FontWeight.w600))),
                      Text('.', style: TextStyle(color: c.muted, fontSize: 12.5)),
                    ]),
                  ),
              ]),
            ),
          ),
        ),
      ),
    );
  }
}

class _Rule extends StatelessWidget {
  const _Rule(this.text, this.met);
  final String text;
  final bool met;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(children: [
        Icon(met ? Icons.check_circle : Icons.circle_outlined, size: 16, color: met ? c.greenInk : c.muted),
        const SizedBox(width: 8),
        Text(text, style: TextStyle(color: met ? c.ink : c.muted, fontSize: 13)),
      ]),
    );
  }
}

/// The second step for accounts with two-factor authentication.
class TwoFactorScreen extends StatefulWidget {
  const TwoFactorScreen({super.key, required this.challenge});
  final String challenge;

  @override
  State<TwoFactorScreen> createState() => _TwoFactorScreenState();
}

class _TwoFactorScreenState extends State<TwoFactorScreen> {
  final _code = TextEditingController();
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() { _busy = true; _error = null; });
    try {
      await context.read<AppState>().confirmCode(widget.challenge, _code.text.trim());
      if (mounted) Navigator.of(context).pop();
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    return Scaffold(
      backgroundColor: c.bg,
      appBar: AppBar(backgroundColor: c.bg, title: const Text('Two-factor code')),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Text('Enter the 6-digit code from your authenticator app, or one of your recovery codes.', style: TextStyle(color: c.ink2, height: 1.4)),
            const SizedBox(height: 18),
            if (_error != null) ...[Callout(_error!), const SizedBox(height: 14)],
            TextField(controller: _code, autofocus: true, autocorrect: false, keyboardType: TextInputType.visiblePassword, onSubmitted: (_) => _submit(), decoration: const InputDecoration(labelText: 'Code'), autofillHints: const [AutofillHints.oneTimeCode]),
            const SizedBox(height: 18),
            FilledButton(onPressed: _busy ? null : _submit, child: Text(_busy ? 'Checking…' : 'Continue')),
          ]),
        ),
      ),
    );
  }
}

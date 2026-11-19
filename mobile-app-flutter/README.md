# atwebtech mobile app (Flutter)

iOS and Android app for atwebtech. It opens on **Log in / Create account** (no marketing site) and then gives the same workspace as the website.

## How it works

The app signs in with `POST /api/mobile/login` and gets a device token. Every later request carries that token, and the server treats it exactly like the signed-in website: the app posts to the **same handlers** (`/app/posts`, `/app/community`, `/app/approvals`, …), so plans, roles, limits and messages are identical. Screens read their data from `/api/mobile/*`. Server code: `app/Mobile.php`, `app/Push.php`, `app/Auth.php`.

| Screen | Native in the app |
|---|---|
| Sign in | Log in, create account, two-factor code, forgot password |
| Home | What needs you (failed posts, approvals, comments, empty queue), this week's numbers, quick actions |
| Publish | Queue, Drafts, Approvals, Failed, Sent: edit, delete, retry (one or all), approve, ask for changes, duplicate, recycle (evergreen) |
| Composer | Channels, text with the network's limit, photo / camera / video upload, carousels (up to 10), date and time, repeat, AI writer, draft / approval / schedule |
| Community | Unanswered / Answered / Done, reply, saved replies, AI-suggested reply, mark done, check now |
| Insights | Totals with change, posts per day, best times, hashtags, top posts |
| Notifications | List, mark read, tap to open the right screen and workspace, push |
| More | Workspace switcher, Channels (pause / resume), Media Library (upload, reuse, keep, delete), Automations (on/off, run, delete), Account (name, password, email alerts), Plan & billing, theme, log out |

Some setup pages open from the website **inside the app, already signed in** (`Api.openWeb`, a single-use link from `POST /api/mobile/handoff`): connecting a channel (each network requires its own sign-in page, and Google blocks embedded web views), channel schedule and settings, setting up an automation, Competitors, Link in Bio, Team, Client workspaces, link tracking and branded reports, API & webhooks, two-factor setup, full reports.

**Payments** open `atwebtech.us/app/billing` in the phone's browser, signed in, where Stripe handles the card. See "Before the stores" below.

## Run it

```bash
flutter pub get
```

```bash
flutter run --dart-define=API_BASE=https://atwebtech.us
```

Against a local server (`php -S localhost:8000 -t public public/index.php` in the repo root): use `--dart-define=API_BASE=http://localhost:8000` on the iOS simulator and `http://10.0.2.2:8000` on the Android emulator.

## Push notifications (Firebase)

The app builds and runs without push. To turn it on:

1. Create a Firebase project, add an **Android app** (`us.atwebtech.atwebtech`) and an **iOS app** (same bundle id).
2. iOS: in Apple Developer create an APNs key and upload it in Firebase → Project settings → Cloud Messaging. In Xcode open `ios/Runner.xcworkspace` → Signing & Capabilities → add **Push Notifications**.
3. Build with the values from Firebase → Project settings:

```bash
flutter build apk --dart-define=API_BASE=https://atwebtech.us --dart-define=FIREBASE_API_KEY=… --dart-define=FIREBASE_PROJECT_ID=… --dart-define=FIREBASE_SENDER_ID=… --dart-define=FIREBASE_ANDROID_APP_ID=… --dart-define=FIREBASE_IOS_APP_ID=…
```

4. Server: Firebase → Project settings → Service accounts → **Generate new private key**. Upload the JSON to the server as `storage/firebase-service-account.json` (or set `FIREBASE_SERVICE_ACCOUNT` to its path in `.env`). Keep it out of git.

Every notification the website creates (failed post, channel to reconnect, approval needed or decided, empty queue, Monday summary) is then also pushed to the person's phones. Tapping one opens Notifications.

## Tests

`integration_test/app_test.dart` signs in and walks through every screen against a real server with a seeded test account, saving screenshots:

```bash
flutter drive --driver=test_driver/integration_test.dart --target=integration_test/app_test.dart --dart-define=API_BASE=http://localhost:8000 --dart-define=TEST_EMAIL=… --dart-define=TEST_PASSWORD=…
```

## Before the stores

- **App icon and splash**: still Flutter's defaults. Replace them (the `flutter_launcher_icons` package does it from one PNG).
- **Signing**: set your Apple team in Xcode, and an upload keystore for Android (`android/app/build.gradle.kts`).
- **Payments policy**: Apple and Google require their own in-app purchase for digital subscriptions bought inside an app, with exceptions that depend on the country (in the US, apps may link out to a website to pay). This app links out to Stripe on the website. Check the current App Store and Google Play rules for the countries you publish in before submitting; if needed, hide the upgrade button for those stores (`PlanScreen` in `lib/screens/more.dart`).
- **Review account**: stores ask for a test login. Create one on the site.

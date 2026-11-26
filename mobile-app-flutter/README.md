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

## Look, motion and sound

- **Themes**: light and dark use the same color tokens as the website panel (`lib/theme.dart` mirrors `public/assets/css/theme.css`). The sun/moon button in the top bar (and on the sign-in screen) switches; More → Theme also offers Auto, which follows the phone.
- **Brand**: the logo mark and the two typefaces (Figtree, Plus Jakarta Sans) are the website panel's own files. The logo with its wordmark sits top-left on Home and on the sign-in screen, the app icon is the mark on white, and the app opens on a brand-green splash (`lib/screens/splash.dart`) that continues from the phone's own launch screen in the same green. On dark backgrounds `assets/logo-dark.png` is used, where the mark's dark half is drawn light.
- **Motion** (`lib/motion.dart`): screens and list rows fade up in order the first time their content arrives, numbers count up, chart bars grow, loading shows shimmering placeholder cards, tappable cards give under the finger, and a check mark pops after a post is scheduled or a reply is sent. Everything is skipped when the phone has Reduce Motion on.
- **Sound** (`lib/fx.dart`, `assets/sounds/`): short sounds with matching haptics for taps, switches, success, sending and errors. More → Sound effects turns them off. They stay quiet when the phone is on silent and never interrupt music. The sounds are synthesized (no third-party samples).

## Run it

```bash
flutter pub get
```

```bash
flutter run --dart-define=API_BASE=https://atwebtech.us
```

Against a local server (`php -S localhost:8000 -t public public/index.php` in the repo root): use `--dart-define=API_BASE=http://localhost:8000` on the iOS simulator and `http://10.0.2.2:8000` on the Android emulator.

## Analytics (Firebase, iOS)

The iPhone and iPad app reports to the Google Analytics property "atwebtech.us" through the Firebase project `atwebtechus-91c72` (Google account atwebtech.us@gmail.com). `ios/Runner/GoogleService-Info.plist` holds that project's identifiers and `lib/main.dart` starts Firebase from it at launch. Android is skipped until an Android app is added to the same project.

Because Firebase now starts from that file, push notifications on iOS use the same project: set the server's `FIREBASE_*` values from `atwebtechus-91c72`, not from another project.

When releasing a version with analytics, update the App Store privacy label (App Privacy) to declare the data Firebase Analytics collects.

## Push notifications (Firebase)

The app is built ready for push (iOS Push Notifications capability, background delivery, Android permission). It stays quiet until the server has a Firebase project to send through. Nothing in the app needs rebuilding to turn it on: the app gets the Firebase values from the server each time it starts.

1. Create a Firebase project. Add an **iOS app** with bundle ID `us.atwebtech.app` and an **Android app** with package `us.atwebtech.atwebtech`.
2. iOS: in Apple Developer, create an APNs key (Keys, Apple Push Notifications service) and upload it in Firebase, Project settings, Cloud Messaging.
3. Server `.env`, from Firebase, Project settings, General: `FIREBASE_API_KEY` (Web API key), `FIREBASE_SENDER_ID` (project number), `FIREBASE_IOS_APP_ID` and `FIREBASE_ANDROID_APP_ID` (each app's App ID).
4. Server: Firebase, Project settings, Service accounts, **Generate new private key**. Upload the JSON to the server as `storage/firebase-service-account.json` (or set `FIREBASE_SERVICE_ACCOUNT` to its path). Keep it out of git.

The admin panel's **Push** page (`/admin/push`) shows what is still missing, how many phones can be reached, and sends an update to everyone using the app, or only iPhone or Android. An update can also be added to each person's Notifications list in the app, which works even before push is set up.

Every notification the website creates (failed post, channel to reconnect, approval needed or decided, empty queue, Monday summary) is pushed to the person's phones too. Tapping a notification opens the screen it is about.

## Tests

`integration_test/app_test.dart` uses the whole app on a simulator against a real (local) server with a seeded test account: sign-in errors, forgot password, creating an account, every Publish tab and its actions (edit, delete, retry, approve, ask for changes, duplicate, recycle), the composer with the AI writer, repeat and date, the Community inbox (AI suggestion, saved reply, send, mark done), Insights, Notifications, Channels, Automations, Library, Account, Plan, switching workspace, sound, both themes and log out. It saves a screenshot of each screen.

```bash
flutter drive --driver=test_driver/integration_test.dart --target=integration_test/app_test.dart --dart-define=API_BASE=http://localhost:8000 --dart-define=TEST_EMAIL=… --dart-define=TEST_PASSWORD=… --dart-define=NEW_EMAIL=…
```

Start the local server with `MAIL_ENABLED=false`, and with `OPENAI_TEST_BASE` and `NETWORK_TEST_BASE` pointing at a fake that answers every request, so nothing reaches OpenAI or a social network. `test/api_test.dart` covers the server calls (including photo upload) without a simulator.

## Before the stores

- **App icon**: made from the panel's logo mark (`assets/app-icon.png` is the 1024px master). Android uses a plain square icon. Add an adaptive icon if you want the system shapes.
- **Signing**: set your Apple team in Xcode, and an upload keystore for Android (`android/app/build.gradle.kts`).
- **Payments policy**: Apple and Google require their own in-app purchase for digital subscriptions bought inside an app, with exceptions that depend on the country (in the US, apps may link out to a website to pay). This app links out to Stripe on the website. Check the current App Store and Google Play rules for the countries you publish in before submitting; if needed, hide the upgrade button for those stores (`PlanScreen` in `lib/screens/more.dart`).
- **Review account**: stores ask for a test login. Create one on the site.

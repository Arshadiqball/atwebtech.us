<?php
/**
 * WordGlow push console: atwebtech.us/wordglow/admin
 *
 * Sends push notifications to installed WordGlow apps through Firebase Cloud
 * Messaging (HTTP v1). Devices subscribe to the topic "all_players"; testers
 * (debug builds, or 5 taps on the settings footer) also join "testers".
 *
 * Server-only setup (never commit these; storage/ and .env are not deployed):
 *   storage/wordglow/service-account.json   Firebase → Project settings → Service accounts → Generate new private key
 *   .env:  WORDGLOW_ADMIN_PASSWORD=<a long password>
 */

declare(strict_types=1);

const WG_TOPICS = ['testers' => 'Testers only', 'all_players' => 'All players'];
const WG_OPENS = ['daily' => "Today's puzzle", 'timed' => 'Beat the Clock', 'levels' => 'Play by level', 'url' => 'A web link'];
const WG_TYPES = ['challenge' => '🎯 Challenge', 'word' => '📖 Word of the day', 'streak' => '🔥 Streak nudge', 'update' => '🚀 App update'];

$base = dirname(__DIR__, 3);
$dir = $base . '/storage/wordglow';
$keyFile = $dir . '/service-account.json';
$logFile = $dir . '/push-log.json';
$tokenFile = $dir . '/fcm-token.json';
$lockFile = $dir . '/login-attempts.json';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; img-src 'self' data:");

session_name('wgadmin');
session_set_cookie_params(['lifetime' => 0, 'path' => '/wordglow/admin', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Strict']);
session_start();

function h(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

function wg_env(string $base, string $name): ?string
{
    $v = getenv($name);
    if (is_string($v) && $v !== '') return $v;
    $file = $base . '/.env';
    if (!is_readable($file)) return null;
    foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (preg_match('/^\s*' . preg_quote($name, '/') . '\s*=\s*(.*)\s*$/', $line, $m)) {
            return trim($m[1], " \t\"'");
        }
    }
    return null;
}

function read_json(string $file, $default)
{
    if (!is_readable($file)) return $default;
    $d = json_decode((string) file_get_contents($file), true);
    return $d ?? $default;
}

function write_json(string $file, $data): void
{
    if (!is_dir(dirname($file))) @mkdir(dirname($file), 0750, true);
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function b64url(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }

function http_post(string $url, array $headers, string $body): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
    ]);
    $out = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [$code, $out === false ? $err : (string) $out];
}

/** OAuth access token for FCM, from the service account (cached ~55 min). */
function fcm_token(array $sa, string $cacheFile): string
{
    $c = read_json($cacheFile, []);
    if (!empty($c['token']) && ($c['exp'] ?? 0) > time() + 60) return $c['token'];
    $now = time();
    $jwt = b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . b64url(json_encode([
        'iss' => $sa['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600,
    ]));
    $key = openssl_pkey_get_private($sa['private_key'] ?? '');
    if (!$key || !openssl_sign($jwt, $sig, $key, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('The service-account private key could not be read.');
    }
    $jwt .= '.' . b64url($sig);
    [$code, $body] = http_post('https://oauth2.googleapis.com/token', ['Content-Type: application/x-www-form-urlencoded'],
        http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt]));
    $d = json_decode($body, true);
    if ($code !== 200 || empty($d['access_token'])) {
        throw new RuntimeException('Google sign-in failed (' . $code . '): ' . substr($body, 0, 300));
    }
    write_json($cacheFile, ['token' => $d['access_token'], 'exp' => $now + (int) ($d['expires_in'] ?? 3600)]);
    @chmod($cacheFile, 0600);
    return $d['access_token'];
}

function fcm_send(array $sa, string $tokenFile, array $msg): string
{
    $token = fcm_token($sa, $tokenFile);
    [$code, $body] = http_post(
        'https://fcm.googleapis.com/v1/projects/' . rawurlencode($sa['project_id']) . '/messages:send',
        ['Authorization: Bearer ' . $token, 'Content-Type: application/json; charset=utf-8'],
        json_encode(['message' => $msg], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
    $d = json_decode($body, true);
    if ($code !== 200) {
        throw new RuntimeException('FCM rejected the message (' . $code . '): ' . ($d['error']['message'] ?? substr($body, 0, 300)));
    }
    return (string) ($d['name'] ?? '');
}

// ---------------------------------------------------------------- state
$password = wg_env($base, 'WORDGLOW_ADMIN_PASSWORD');
$configured = $password !== null && strlen($password) >= 10;
$sa = read_json($keyFile, null);
$hasKey = is_array($sa) && !empty($sa['client_email']) && !empty($sa['private_key']) && !empty($sa['project_id']);
$flash = null; // [ok(bool), text]
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrfOk = fn(): bool => hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''));

// ---------------------------------------------------------------- login / logout
$ip = $_SERVER['REMOTE_ADDR'] ?? '?';
$attempts = array_filter(read_json($lockFile, []), fn($t) => $t > time() - 900);
$myFails = count(array_filter($attempts, fn($t, $k) => str_starts_with((string) $k, $ip . '|'), ARRAY_FILTER_USE_BOTH));

if (($_POST['action'] ?? '') === 'login' && $configured) {
    if ($myFails >= 5) {
        $flash = [false, 'Too many tries. Wait 15 minutes.'];
    } elseif ($csrfOk() && hash_equals($password, (string) ($_POST['password'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION['wg_admin'] = true;
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
        header('Location: ./'); exit;
    } else {
        $attempts[$ip . '|' . microtime(true)] = time();
        write_json($lockFile, $attempts);
        usleep(600000);
        $flash = [false, 'Wrong password.'];
    }
}
if (($_POST['action'] ?? '') === 'logout' && $csrfOk()) {
    $_SESSION = [];
    session_destroy();
    header('Location: ./'); exit;
}
$authed = $configured && !empty($_SESSION['wg_admin']);

// ---------------------------------------------------------------- send
$form = ['type' => 'challenge', 'title' => '', 'body' => '', 'open' => 'daily', 'url' => '', 'topic' => 'testers'];
if ($authed && ($_POST['action'] ?? '') === 'send') {
    foreach ($form as $k => $_) $form[$k] = trim((string) ($_POST[$k] ?? $form[$k]));
    $errors = [];
    if (!$csrfOk()) $errors[] = 'Your session expired. Reload the page.';
    if (!$hasKey) $errors[] = 'The Firebase key is not on the server yet.';
    if ($form['title'] === '' || mb_strlen($form['title']) > 65) $errors[] = 'Subject: 1 to 65 characters.';
    if ($form['body'] === '' || mb_strlen($form['body']) > 240) $errors[] = 'Message: 1 to 240 characters.';
    if (!isset(WG_TOPICS[$form['topic']])) $errors[] = 'Pick who receives it.';
    if (!isset(WG_OPENS[$form['open']])) $form['open'] = 'daily';
    if (!isset(WG_TYPES[$form['type']])) $form['type'] = 'update';
    if ($form['open'] === 'url' && !preg_match('#^https://\S+$#', $form['url'])) $errors[] = 'The link must start with https://';
    if ($form['topic'] === 'all_players' && ($_POST['confirm_all'] ?? '') !== '1') $errors[] = 'Tick the box to confirm sending to all players.';

    if ($errors) {
        $flash = [false, implode(' ', $errors)];
    } else {
        $data = ['type' => $form['type'], 'open' => $form['open']];
        if ($form['open'] === 'url') $data['url'] = $form['url'];
        $msg = [
            'topic' => $form['topic'],
            'notification' => ['title' => $form['title'], 'body' => $form['body']],
            'data' => $data,
            'android' => [
                'priority' => 'high',
                'ttl' => '86400s',
                'notification' => ['channel_id' => 'news', 'icon' => 'ic_stat_notify', 'color' => '#8B5CF6'],
            ],
        ];
        $log = read_json($logFile, []);
        $entry = ['at' => gmdate('c'), 'topic' => $form['topic'], 'type' => $form['type'], 'title' => $form['title'], 'body' => $form['body'], 'open' => $form['open']];
        try {
            $entry['id'] = fcm_send($sa, $tokenFile, $msg);
            $entry['ok'] = true;
            $flash = [true, 'Sent to ' . WG_TOPICS[$form['topic']] . '. Phones usually show it within a minute.'];
            if ($form['topic'] === 'all_players') $form = array_merge($form, ['title' => '', 'body' => '', 'topic' => 'testers']);
        } catch (Throwable $e) {
            $entry['ok'] = false;
            $entry['error'] = $e->getMessage();
            $flash = [false, $e->getMessage()];
        }
        array_unshift($log, $entry);
        write_json($logFile, array_slice($log, 0, 200));
    }
}
$history = $authed ? array_slice(read_json($logFile, []), 0, 30) : [];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>WordGlow Push Console</title>
<link rel="icon" href="/wordglow/img/favicon.png" type="image/png">
<style>
  :root { --bg:#0A0722; --cyan:#22D3EE; --violet:#8B5CF6; --pink:#EC4899; --green:#10B981; --red:#F87171; --text:#fff; --dim:rgba(255,255,255,.65); --line:rgba(255,255,255,.16); }
  * { box-sizing:border-box; }
  body { margin:0; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; color:var(--text); background:var(--bg);
         background-image:radial-gradient(60vmax 60vmax at 0% 0%,rgba(34,211,238,.18),transparent 60%),radial-gradient(60vmax 60vmax at 100% 40%,rgba(236,72,153,.16),transparent 60%);
         background-attachment:fixed; line-height:1.45; }
  .wrap { max-width:1040px; margin:0 auto; padding:16px; }
  header { display:flex; align-items:center; gap:12px; padding:8px 0 20px; }
  header img { width:40px; height:40px; border-radius:11px; }
  header b { font-size:20px; font-weight:900; } header span { color:var(--dim); font-size:14px; }
  header form { margin-left:auto; }
  .card { padding:20px; border-radius:20px; background:linear-gradient(135deg,rgba(255,255,255,.10),rgba(255,255,255,.03)); border:1px solid var(--line); }
  .grid { display:grid; grid-template-columns:1.25fr .75fr; gap:18px; align-items:start; }
  h2 { margin:0 0 14px; font-size:18px; }
  label { display:block; font-weight:700; font-size:14px; margin:14px 0 6px; }
  label small { color:var(--dim); font-weight:400; float:right; }
  input[type=text], input[type=url], input[type=password], textarea, select {
    width:100%; padding:12px 14px; border-radius:12px; border:1px solid var(--line); background:rgba(0,0,0,.35); color:#fff; font:inherit; }
  textarea { min-height:96px; resize:vertical; }
  input:focus, textarea:focus, select:focus { outline:2px solid var(--violet); border-color:transparent; }
  .row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  .chips { display:flex; flex-wrap:wrap; gap:8px; }
  .chip { padding:8px 12px; border-radius:999px; border:1px solid var(--line); background:rgba(255,255,255,.06); color:#fff; font:inherit; font-size:13px; cursor:pointer; }
  .chip:hover { border-color:var(--cyan); }
  .seg { display:flex; gap:8px; } .seg label { flex:1; margin:0; font-weight:600; padding:12px; border-radius:12px; border:1px solid var(--line); cursor:pointer; text-align:center; }
  .seg input { display:none; } .seg input:checked + span { color:var(--cyan); } .seg label:has(input:checked) { border-color:var(--cyan); background:rgba(34,211,238,.10); }
  .confirm { display:none; margin-top:12px; padding:12px; border-radius:12px; background:rgba(245,158,11,.12); border:1px solid rgba(245,158,11,.5); font-size:14px; }
  .confirm.show { display:block; }
  button.primary { margin-top:18px; width:100%; padding:14px; border:0; border-radius:14px; font:inherit; font-weight:800; font-size:16px; color:#fff; cursor:pointer;
    background:linear-gradient(90deg,var(--cyan),var(--violet),var(--pink)); }
  button.ghost { padding:8px 14px; border-radius:999px; border:1px solid var(--line); background:none; color:var(--dim); font:inherit; cursor:pointer; }
  .flash { padding:12px 14px; border-radius:12px; margin-bottom:16px; font-weight:600; }
  .flash.ok { background:rgba(16,185,129,.15); border:1px solid var(--green); } .flash.bad { background:rgba(248,113,113,.12); border:1px solid var(--red); }
  .phone { border-radius:26px; padding:14px; background:#16131f; border:1px solid var(--line); }
  .notif { background:#2a2638; border-radius:16px; padding:12px 14px; display:grid; grid-template-columns:28px 1fr; gap:10px; }
  .notif .ic { width:28px; height:28px; border-radius:8px; background:var(--violet); display:grid; place-items:center; font-weight:900; font-size:15px; }
  .notif .app { color:var(--dim); font-size:12px; } .notif .t { font-weight:700; margin-top:2px; word-break:break-word; } .notif .b { color:#ddd; font-size:14px; word-break:break-word; }
  .hint { color:var(--dim); font-size:13px; margin:10px 2px 0; }
  .status li { margin:4px 0; font-size:14px; } .status { padding-left:18px; margin:0; }
  table { width:100%; border-collapse:collapse; font-size:14px; }
  td, th { text-align:left; padding:10px 8px; border-top:1px solid var(--line); vertical-align:top; }
  th { color:var(--dim); font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:.04em; border-top:0; }
  .pill { font-size:12px; padding:2px 8px; border-radius:999px; border:1px solid var(--line); white-space:nowrap; }
  .ok { color:#6ee7b7; } .bad { color:var(--red); }
  .tablewrap { overflow-x:auto; }
  .login { max-width:380px; margin:12vh auto; }
  @media (max-width:820px) { .grid { grid-template-columns:1fr; } .row { grid-template-columns:1fr; } }
</style>
</head>
<body>
<div class="wrap">
<?php if (!$authed): ?>
  <div class="card login">
    <header style="padding-top:0"><img src="/wordglow/img/logo.png" alt=""><div><b>WordGlow</b><br><span>Push console</span></div></header>
    <?php if ($flash): ?><div class="flash <?= $flash[0] ? 'ok' : 'bad' ?>"><?= h($flash[1]) ?></div><?php endif; ?>
    <?php if (!$configured): ?>
      <p>This page is locked until a password is set on the server.</p>
      <p class="hint">Add <code>WORDGLOW_ADMIN_PASSWORD=…</code> (10+ characters) to the site's <code>.env</code> file, then reload.</p>
    <?php else: ?>
      <form method="post" autocomplete="off">
        <input type="hidden" name="action" value="login"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
        <label for="pw">Password</label>
        <input id="pw" type="password" name="password" required autofocus>
        <button class="primary" type="submit">Sign in</button>
      </form>
    <?php endif; ?>
  </div>
<?php else: ?>
  <header>
    <img src="/wordglow/img/logo.png" alt="">
    <div><b>WordGlow Push Console</b><br><span>Send challenges and news to every installed app</span></div>
    <form method="post"><input type="hidden" name="action" value="logout"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><button class="ghost">Sign out</button></form>
  </header>

  <?php if ($flash): ?><div class="flash <?= $flash[0] ? 'ok' : 'bad' ?>"><?= h($flash[1]) ?></div><?php endif; ?>
  <?php if (!$hasKey): ?>
    <div class="flash bad">Firebase key missing: upload the service-account JSON to <code>storage/wordglow/service-account.json</code> on the server.</div>
  <?php endif; ?>

  <div class="grid">
    <form class="card" method="post" id="f">
      <input type="hidden" name="action" value="send"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <h2>New push</h2>
      <div class="chips" aria-label="Templates">
        <button type="button" class="chip" data-t="challenge" data-title="🎯 Today's word is a tough one!" data-body="Only 1 in 3 players solved it in 3 tries. Can you?" data-open="daily">🎯 Daily challenge</button>
        <button type="button" class="chip" data-t="challenge" data-title="⏱️ 90-second challenge" data-body="How many words can you crack before the clock runs out? Beat your best today." data-open="timed">⏱️ Beat the Clock</button>
        <button type="button" class="chip" data-t="word" data-title="📖 Word of the day" data-body="Learn a new word in under a minute. Today's puzzle is waiting." data-open="daily">📖 Word of the day</button>
        <button type="button" class="chip" data-t="streak" data-title="🔥 Don't break your streak!" data-body="One quick word keeps your streak alive. It takes a minute." data-open="daily">🔥 Streak nudge</button>
        <button type="button" class="chip" data-t="challenge" data-title="💎 Weekend challenge: Advanced level" data-body="7-letter words, 6 tries. Only word masters finish this one." data-open="levels">💎 Weekend level</button>
        <button type="button" class="chip" data-t="update" data-title="🚀 WordGlow just got better" data-body="New words, smoother play and fixes. Update from Google Play to get it." data-open="url" data-url="https://play.google.com/store/apps/details?id=com.atwebtech.wordglow">🚀 App update</button>
      </div>

      <label for="type">Type</label>
      <select id="type" name="type"><?php foreach (WG_TYPES as $k => $v): ?><option value="<?= h($k) ?>" <?= $form['type'] === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select>

      <label for="title">Subject <small id="tc">0/65</small></label>
      <input id="title" type="text" name="title" maxlength="65" required value="<?= h($form['title']) ?>" placeholder="🎯 Can you beat today's word?">

      <label for="body">Message <small id="bc">0/240</small></label>
      <textarea id="body" name="body" maxlength="240" required placeholder="Only 1 in 3 players solved it in 3 tries."><?= h($form['body']) ?></textarea>

      <div class="row">
        <div>
          <label for="open">Tapping it opens</label>
          <select id="open" name="open"><?php foreach (WG_OPENS as $k => $v): ?><option value="<?= h($k) ?>" <?= $form['open'] === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select>
        </div>
        <div id="urlbox">
          <label for="url">Link</label>
          <input id="url" type="url" name="url" value="<?= h($form['url']) ?>" placeholder="https://…">
        </div>
      </div>

      <label>Send to</label>
      <div class="seg">
        <?php foreach (WG_TOPICS as $k => $v): ?>
          <label><input type="radio" name="topic" value="<?= h($k) ?>" <?= $form['topic'] === $k ? 'checked' : '' ?>><span><?= $k === 'testers' ? '🧪 ' : '📣 ' ?><?= h($v) ?></span></label>
        <?php endforeach; ?>
      </div>
      <div class="confirm" id="confirm">
        <label style="margin:0;font-weight:600"><input type="checkbox" name="confirm_all" value="1"> I checked the text. Send it to every player now.</label>
      </div>
      <button class="primary" type="submit" id="send">Send push</button>
      <p class="hint">Tip: send to Testers first. To make your phone a tester, open WordGlow → Settings and tap the small text at the bottom 5 times.</p>
    </form>

    <div>
      <div class="card">
        <h2>Preview</h2>
        <div class="phone"><div class="notif"><div class="ic">W</div><div><div class="app">WordGlow · now</div><div class="t" id="pt">Subject</div><div class="b" id="pb">Message</div></div></div></div>
        <p class="hint">Android shows about 40 characters of the subject and 2 lines of the message before it's expanded.</p>
      </div>
      <div class="card" style="margin-top:18px">
        <h2>Good practice</h2>
        <ul class="status">
          <li>2–4 pushes a week at most. More makes people turn them off.</li>
          <li>Best times: 9 am or 7 pm in your players' time zone.</li>
          <li>Players can turn these off in the app's Settings (Challenges &amp; news).</li>
        </ul>
      </div>
    </div>
  </div>

  <div class="card" style="margin-top:18px">
    <h2>History</h2>
    <?php if (!$history): ?><p class="hint">Nothing sent yet.</p><?php else: ?>
    <div class="tablewrap"><table>
      <tr><th>When (UTC)</th><th>To</th><th>Push</th><th>Opens</th><th>Result</th></tr>
      <?php foreach ($history as $e): ?>
        <tr>
          <td><?= h(substr(str_replace('T', ' ', $e['at']), 0, 16)) ?></td>
          <td><span class="pill"><?= h(WG_TOPICS[$e['topic']] ?? $e['topic']) ?></span></td>
          <td><b><?= h($e['title']) ?></b><br><span style="color:var(--dim)"><?= h($e['body']) ?></span></td>
          <td><?= h(WG_OPENS[$e['open']] ?? $e['open']) ?></td>
          <td><?= !empty($e['ok']) ? '<span class="ok">Sent</span>' : '<span class="bad" title="' . h($e['error'] ?? '') . '">Failed</span>' ?></td>
        </tr>
      <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
  </div>
<?php endif; ?>
</div>
<?php if ($authed): ?>
<script>
  const $ = (id) => document.getElementById(id);
  const sync = () => {
    $('pt').textContent = $('title').value || 'Subject';
    $('pb').textContent = $('body').value || 'Message';
    $('tc').textContent = $('title').value.length + '/65';
    $('bc').textContent = $('body').value.length + '/240';
    $('urlbox').style.visibility = $('open').value === 'url' ? 'visible' : 'hidden';
    const all = document.querySelector('input[name=topic]:checked')?.value === 'all_players';
    $('confirm').classList.toggle('show', all);
    $('send').textContent = all ? 'Send to ALL players' : 'Send test push';
  };
  ['title', 'body', 'open'].forEach((id) => $(id).addEventListener('input', sync));
  document.querySelectorAll('input[name=topic]').forEach((r) => r.addEventListener('change', sync));
  document.querySelectorAll('.chip').forEach((c) => c.addEventListener('click', () => {
    $('type').value = c.dataset.t; $('title').value = c.dataset.title; $('body').value = c.dataset.body;
    $('open').value = c.dataset.open; $('url').value = c.dataset.url || ''; sync();
  }));
  sync();
</script>
<?php endif; ?>
</body>
</html>

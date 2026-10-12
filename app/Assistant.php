<?php

declare(strict_types=1);

/** Composer AI Assistant. Calls OpenAI from the server; the key never reaches the browser. */
final class Assistant
{
    private const PROMPT_MAX = 1000;

    public static function enabled(): bool
    {
        return config('openai.key') !== '';
    }

    public static function generate(): never
    {
        $user = Auth::member();
        if (!self::enabled()) {
            json_response(['status' => 'error', 'message' => 'The AI Assistant isn’t switched on for this workspace yet.'], 503);
        }
        $prompt = trim((string) ($_POST['prompt'] ?? ''));
        if ($prompt === '') {
            json_response(['status' => 'error', 'message' => 'Tell the assistant what the post is about.'], 422);
        }
        if (mb_strlen($prompt) > self::PROMPT_MAX) {
            json_response(['status' => 'error', 'message' => sprintf('Keep the brief under %d characters.', self::PROMPT_MAX)], 422);
        }
        $network = (string) ($_POST['network'] ?? '');
        $names = array_column(data('integrations'), 'name', 'slug');
        $network = $names[$network] ?? 'social media';
        $limit = data('workspace')['limits'][$_POST['network'] ?? ''] ?? data('workspace')['post_max'];

        $fp = 'user:' . (int) $user['id'];
        if (RateLimiter::tooManyAttempts('ai-minute', $fp, 6, 1)) {
            json_response(['status' => 'error', 'message' => 'One moment — try again in a minute.'], 429);
        }
        if (RateLimiter::tooManyAttempts('ai-day', $fp, (int) config('openai.daily_limit'), 1440)) {
            json_response(['status' => 'error', 'message' => 'You’ve used today’s AI generations. They reset over the next 24 hours.'], 429);
        }
        RateLimiter::hit('ai-minute', $fp);
        RateLimiter::hit('ai-day', $fp);

        try {
            $text = self::complete([
                ['role' => 'system', 'content' => sprintf(
                    'You write %s posts for small businesses. Reply with the post text only: no preamble, no quotes, no markdown headings. '
                    . 'Keep it under %d characters. Never invent statistics, prices, reviews, awards or guarantees; if a fact is needed and not given, leave a [placeholder].',
                    $network,
                    min((int) $limit, 2200)
                )],
                ['role' => 'user', 'content' => $prompt],
            ]);
        } catch (Throwable $e) {
            error_log(sprintf('[%s] ai generate failed for user %d: %s', gmdate('c'), (int) $user['id'], $e->getMessage()));
            $rejected = $e->getMessage() === 'invalid_api_key';
            json_response([
                'status' => 'error',
                'message' => $rejected
                    ? 'OpenAI rejected the API key on this server. Replace it, then try again.'
                    : 'The AI Assistant couldn’t answer just now. Please try again.',
            ], $rejected ? 401 : 502);
        }
        json_response(['status' => 'ok', 'text' => mb_substr($text, 0, (int) $limit)]);
    }

    private static function complete(array $messages): string
    {
        $test = config('app.is_production') ? '' : rtrim((string) Env::get('OPENAI_TEST_BASE', ''), '/');
        $ch = curl_init(($test !== '' ? $test : 'https://api.openai.com') . '/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 40,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | ($test !== '' ? CURLPROTO_HTTP : 0),
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . config('openai.key')],
            CURLOPT_POSTFIELDS => json_encode(['model' => config('openai.model'), 'messages' => $messages, 'max_tokens' => 700, 'temperature' => 0.8], JSON_THROW_ON_ERROR),
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($body === false) {
            throw new RuntimeException('OpenAI request failed: ' . curl_error($ch));
        }
        $data = json_decode((string) $body, true);
        if ($status !== 200) {
            $code = (string) ($data['error']['code'] ?? '');
            if ($status === 401 || $code === 'invalid_api_key') {
                throw new RuntimeException('invalid_api_key');
            }
            throw new RuntimeException(sprintf('OpenAI HTTP %d %s', $status, mb_substr($code !== '' ? $code : (string) ($data['error']['type'] ?? ''), 0, 80)));
        }
        $text = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
        if ($text === '') {
            throw new RuntimeException('OpenAI returned no text.');
        }
        return $text;
    }
}

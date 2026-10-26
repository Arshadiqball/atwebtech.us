<?php

declare(strict_types=1);

/** Composer AI Assistant. Calls OpenAI from the server; the key never reaches the browser. */
final class Assistant
{
    private const PROMPT_MAX = 1000;
    private const BATCH = 10;
    public const RUN_MAX = 30;

    public const MODES = [
        'random' => ['Random', 'Fresh, varied angles on your topic', 'sparkle'],
        'trending' => ['Trending', 'Ties your topic to today’s Google searches', 'trend-up'],
        'prompt' => ['Prompt', 'Follows your own brief closely', 'note-pencil'],
    ];
    public const TONES = [
        'friendly' => 'friendly and warm', 'professional' => 'clear and professional', 'bold' => 'bold and confident',
        'witty' => 'light and witty', 'inspiring' => 'encouraging and inspiring', 'educational' => 'practical and educational',
    ];
    /** label => [instruction, max characters] */
    public const LENGTHS = [
        'short' => ['short, one to three sentences', 280],
        'medium' => ['medium, one or two short paragraphs', 700],
        'long' => ['long, a few short paragraphs', 1500],
    ];
    public const LANGUAGES = ['English', 'Urdu', 'Roman Urdu', 'Hindi', 'Arabic', 'Spanish', 'French', 'German', 'Portuguese'];
    public const TREND_REGIONS = [
        'US' => 'United States', 'GB' => 'United Kingdom', 'CA' => 'Canada', 'AU' => 'Australia', 'IN' => 'India', 'PK' => 'Pakistan',
        'AE' => 'United Arab Emirates', 'SA' => 'Saudi Arabia', 'DE' => 'Germany', 'FR' => 'France', 'ES' => 'Spain', 'BR' => 'Brazil',
    ];

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

    /** Error message when this many OpenAI calls would pass the user's per-minute or daily allowance, else null. */
    public static function allowanceProblem(int $userId, int $calls): ?string
    {
        $fp = 'user:' . $userId;
        $daily = (int) config('openai.daily_limit');
        if (RateLimiter::tooManyAttempts('ai-minute', $fp, max(1, 7 - $calls), 1)) {
            return 'One moment — try again in a minute.';
        }
        if (RateLimiter::tooManyAttempts('ai-day', $fp, $daily - $calls + 1, 1440)) {
            return $calls > 1
                ? sprintf('That needs %d of your daily AI generations and not enough are left. Ask for fewer posts, or try again later.', $calls)
                : 'You’ve used today’s AI generations. They reset over the next 24 hours.';
        }
        return null;
    }

    /** Region for trending topics, from the time zone's country when Google Trends covers it. */
    public static function trendRegion(DateTimeZone $tz): string
    {
        $code = (string) ($tz->getLocation()['country_code'] ?? '');
        return isset(self::TREND_REGIONS[$code]) ? $code : 'US';
    }

    /**
     * Today's Google Trends searches for a region.
     *
     * @return list<array{topic:string, headline:string}>
     */
    public static function trends(string $region): array
    {
        if (!isset(self::TREND_REGIONS[$region])) {
            throw new InvalidArgumentException('Unknown trends region.');
        }
        $ch = curl_init('https://trends.google.com/trending/rss?geo=' . $region);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => ['Accept: application/rss+xml, text/xml'],
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($body === false || $status !== 200) {
            throw new RuntimeException(sprintf('Google Trends HTTP %d %s', $status, curl_error($ch)));
        }
        $xml = simplexml_load_string((string) $body, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        if ($xml === false) {
            throw new RuntimeException('Google Trends returned unreadable XML.');
        }
        $out = [];
        foreach ($xml->channel->item ?? [] as $item) {
            $topic = trim((string) $item->title);
            if ($topic === '') {
                continue;
            }
            $ht = $item->children('https://trends.google.com/trending/rss');
            $out[] = ['topic' => mb_substr($topic, 0, 100), 'headline' => mb_substr(trim((string) ($ht->news_item[0]->news_item_title ?? '')), 0, 200)];
            if (count($out) === 20) {
                break;
            }
        }
        if (!$out) {
            throw new RuntimeException('Google Trends returned no topics.');
        }
        return $out;
    }

    /**
     * Write a run of distinct posts, in batches of BATCH per OpenAI call.
     *
     * @param array{mode:string, topic:string, details:string, provider:string, tone:string, length:string, language:string,
     *              hashtags:bool, emojis:bool, cta:bool, question:bool, rules:string, dates:list<string>, trends:list<array{topic:string, headline:string}>} $brief
     * @return list<array{title:string, body:string}>
     */
    public static function posts(int $userId, array $brief): array
    {
        $network = data('integrations');
        $network = array_column($network, 'name', 'slug')[$brief['provider']] ?? 'social media';
        $limit = min(2200, (int) (data('workspace')['limits'][$brief['provider']] ?? data('workspace')['post_max']));
        $target = min($limit, self::LENGTHS[$brief['length']][1]);
        $youtube = $brief['provider'] === 'youtube';

        $rules = [
            sprintf('Write in %s. Tone: %s.', $brief['language'], self::TONES[$brief['tone']]),
            sprintf('Each post body is %s and never longer than %d characters.', self::LENGTHS[$brief['length']][0], $target),
            $brief['hashtags'] ? 'End each post with 2 to 4 relevant hashtags.' : 'Do not use hashtags.',
            $brief['emojis'] ? 'Use a few fitting emojis.' : 'Do not use emojis.',
        ];
        if ($brief['cta']) {
            $rules[] = 'Close each post with a clear, low-pressure call to action.';
        }
        if ($brief['question']) {
            $rules[] = 'Ask the audience a question in each post to invite comments.';
        }
        if ($brief['rules'] !== '') {
            $rules[] = 'The account owner’s own rules, which win over style rules above: ' . $brief['rules'];
        }
        $system = sprintf(
            'You write %s posts for a small business account. '
            . 'Never invent statistics, prices, reviews, awards, client names or guarantees; if a fact is needed and not given, leave a [placeholder]. '
            . 'Plain text only: no markdown, no surrounding quotes, no “Post 1” labels. %s '
            . 'Reply with JSON only, shaped {"posts":[{"title":"...","body":"..."}]}. %s',
            $network,
            implode(' ', $rules),
            $youtube ? 'Each title is a specific, honest YouTube video title under 90 characters.' : 'Leave every "title" as an empty string.'
        );

        $context = 'Topic or niche: ' . $brief['topic'] . "\n";
        $context .= match ($brief['mode']) {
            'prompt' => "Brief from the account owner, follow it closely:\n" . $brief['details'] . "\n",
            default => $brief['details'] !== '' ? "About the business and audience:\n" . $brief['details'] . "\n" : '',
        };
        if ($brief['mode'] === 'trending') {
            $context .= "\nToday’s trending Google searches in this region (topic — headline):\n";
            foreach ($brief['trends'] as $t) {
                $context .= '- ' . $t['topic'] . ($t['headline'] !== '' ? ' — ' . $t['headline'] : '') . "\n";
            }
        }

        $posts = [];
        $total = count($brief['dates']);
        while (count($posts) < $total) {
            $dates = array_slice($brief['dates'], count($posts), self::BATCH);
            $ask = $context . "\n" . match ($brief['mode']) {
                'random' => sprintf('Write %d posts. Pick a different, unexpected angle and format for each one (tip, short story, myth vs fact, behind the scenes, checklist, lesson learned, question, prediction).', count($dates)),
                'trending' => sprintf('Write %d posts that tie the topic to the trends above. Only use a trend when the link to the topic is genuine and tasteful; skip tragedies, deaths, crime and politics. Use nothing about a trend beyond what its headline says. Use a different trend for each post where possible; if too few fit, write timely posts about what people in this niche are talking about right now, without inventing news.', count($dates)),
                default => sprintf('Write %d distinct posts that each follow the brief from a different angle.', count($dates)),
            };
            $ask .= "\nThe posts go out on these dates, in order: " . implode(', ', $dates) . '.';
            if ($posts) {
                $ask .= "\nThese posts are already written, so do not repeat their angles or openings:\n";
                foreach ($posts as $p) {
                    $ask .= '- ' . mb_substr(preg_replace('/\s+/', ' ', $p['body']), 0, 90) . "\n";
                }
            }
            RateLimiter::hit('ai-minute', 'user:' . $userId);
            RateLimiter::hit('ai-day', 'user:' . $userId);
            $raw = self::complete([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $ask],
            ], ['max_tokens' => min(12000, 300 + count($dates) * (int) ceil($target / 2.5)), 'timeout' => 90, 'json' => true]);
            $data = json_decode($raw, true);
            $batch = [];
            foreach ((array) ($data['posts'] ?? []) as $p) {
                $body = trim((string) ($p['body'] ?? ''));
                if ($body === '') {
                    continue;
                }
                $batch[] = [
                    'title' => $youtube ? mb_substr(trim((string) ($p['title'] ?? '')), 0, 100) : '',
                    'body' => mb_substr($body, 0, $limit),
                ];
            }
            if (!$batch) {
                throw new RuntimeException('OpenAI returned no usable posts.');
            }
            array_push($posts, ...array_slice($batch, 0, count($dates)));
            if (count($batch) < count($dates)) {
                break;
            }
        }
        return $posts;
    }

    /** @param array{max_tokens?:int, timeout?:int, json?:bool} $options */
    public static function complete(array $messages, array $options = []): string
    {
        $payload = ['model' => config('openai.model'), 'messages' => $messages, 'max_tokens' => $options['max_tokens'] ?? 700, 'temperature' => 0.8];
        if (!empty($options['json'])) {
            $payload['response_format'] = ['type' => 'json_object'];
        }
        $test = config('app.is_production') ? '' : rtrim((string) Env::get('OPENAI_TEST_BASE', ''), '/');
        $ch = curl_init(($test !== '' ? $test : 'https://api.openai.com') . '/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $options['timeout'] ?? 40,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | ($test !== '' ? CURLPROTO_HTTP : 0),
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . config('openai.key')],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
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

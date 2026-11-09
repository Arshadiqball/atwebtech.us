<?php

declare(strict_types=1);

/**
 * Publishing to Facebook Pages, Threads, X and Pinterest.
 * Each method returns ['id' => network post ID, 'url' => public link or ''].
 * Images are handed to Meta and Pinterest as short-lived signed links (Media::shareUrl);
 * X and Facebook videos are uploaded as files.
 */
final class Posting
{
    private const GRAPH = 'https://graph.facebook.com/v23.0';
    private const GRAPH_VIDEO = 'https://graph-video.facebook.com/v23.0';
    private const THREADS = 'https://graph.threads.net/v1.0';
    private const X = 'https://api.x.com';
    private const PINTEREST = 'https://api.pinterest.com';

    /** @param array|null $media a ready media row, with $file its path on disk */
    public static function facebook(string $pageToken, string $pageId, string $text, ?array $media, ?string $file): array
    {
        if (!preg_match('/^[0-9]+$/', $pageId)) {
            throw new PublishError('This Facebook Page can’t be published to. Reconnect it and try again.');
        }
        if ($media === null && trim($text) === '') {
            throw new PublishError('Facebook posts need some text, a photo, or a video.');
        }
        if ($media !== null && $file === null) {
            throw new PublishError('The attached file is missing. Attach it again, then retry.');
        }
        if ($media !== null && $media['kind'] === 'video') {
            $sent = self::send('POST', self::base(self::GRAPH_VIDEO) . '/' . $pageId . '/videos', [
                'access_token' => $pageToken,
                'description' => $text,
                'source' => new CURLFile((string) $file, (string) $media['mime'], 'video'),
            ], timeout: 0);
            $id = (string) ($sent['id'] ?? '');
            self::requireId($id, 'Facebook didn’t accept the video.');
            return ['id' => $id, 'url' => 'https://www.facebook.com/' . $pageId . '/videos/' . $id];
        }
        if ($media !== null) {
            $sent = self::send('POST', self::base(self::GRAPH) . '/' . $pageId . '/photos', [
                'access_token' => $pageToken,
                'url' => Media::shareUrl((int) $media['id']),
                'message' => $text,
            ]);
            $id = (string) ($sent['post_id'] ?? ($sent['id'] ?? ''));
        } else {
            $sent = self::send('POST', self::base(self::GRAPH) . '/' . $pageId . '/feed', [
                'access_token' => $pageToken,
                'message' => $text,
            ]);
            $id = (string) ($sent['id'] ?? '');
        }
        self::requireId($id, 'Facebook didn’t accept the post.');
        $url = '';
        try {
            $info = self::send('GET', self::base(self::GRAPH) . '/' . rawurlencode($id) . '?' . http_build_query(['fields' => 'permalink_url', 'access_token' => $pageToken]));
            $url = self::safeUrl((string) ($info['permalink_url'] ?? ''), ['www.facebook.com', 'facebook.com']);
        } catch (Throwable) {
        }
        return ['id' => $id, 'url' => $url];
    }

    public static function threads(string $token, string $userId, string $text, ?array $media, ?string $file): array
    {
        if (!preg_match('/^[0-9]+$/', $userId)) {
            throw new PublishError('This Threads profile can’t be published to. Reconnect it and try again.');
        }
        if ($media !== null && $media['kind'] !== 'image') {
            throw new PublishError('Threads posts from atwebtech can include text and a photo, not a video. Attach a photo or remove the file, then retry.');
        }
        if ($media !== null && $file === null) {
            throw new PublishError('The photo is missing. Attach it again, then retry.');
        }
        if ($media === null && trim($text) === '') {
            throw new PublishError('Threads posts need some text or a photo.');
        }
        $fields = ['access_token' => $token, 'text' => mb_substr($text, 0, 500)];
        $fields += $media !== null
            ? ['media_type' => 'IMAGE', 'image_url' => Media::shareUrl((int) $media['id'])]
            : ['media_type' => 'TEXT'];
        $created = self::send('POST', self::base(self::THREADS) . '/' . $userId . '/threads', $fields);
        $creation = (string) ($created['id'] ?? '');
        self::requireId($creation, 'Threads didn’t accept the post.');
        if ($media !== null) {
            $status = '';
            for ($i = 0; $i < 10; $i++) {
                $check = self::send('GET', self::base(self::THREADS) . '/' . $creation . '?' . http_build_query(['fields' => 'status', 'access_token' => $token]));
                $status = (string) ($check['status'] ?? '');
                if (in_array($status, ['FINISHED', 'ERROR', 'EXPIRED', 'PUBLISHED'], true)) {
                    break;
                }
                self::pause(3);
            }
            if ($status === 'ERROR' || $status === 'EXPIRED') {
                throw new PublishError('Threads couldn’t use that photo. Attach a JPG or PNG under 8 MB, then retry.');
            }
            if ($status !== 'FINISHED') {
                throw new ChannelApiException('Threads is still processing the photo.', 0);
            }
        }
        $published = self::send('POST', self::base(self::THREADS) . '/' . $userId . '/threads_publish', ['access_token' => $token, 'creation_id' => $creation]);
        $id = (string) ($published['id'] ?? '');
        self::requireId($id, 'Threads didn’t publish the post.');
        $url = '';
        try {
            $info = self::send('GET', self::base(self::THREADS) . '/' . $id . '?' . http_build_query(['fields' => 'permalink', 'access_token' => $token]));
            $url = self::safeUrl((string) ($info['permalink'] ?? ''), ['www.threads.net', 'threads.net', 'www.threads.com', 'threads.com']);
        } catch (Throwable) {
        }
        return ['id' => $id, 'url' => $url];
    }

    public static function x(string $token, string $scopes, string $text, ?array $media, ?string $file): array
    {
        if ($media === null && trim($text) === '') {
            throw new PublishError('Posts on X need some text or a photo.');
        }
        $payload = ['text' => $text];
        if ($media !== null) {
            if ($media['kind'] !== 'image') {
                throw new PublishError('Posts on X from atwebtech can include a photo, not a video. Attach a photo or remove the file, then retry.');
            }
            if ($file === null) {
                throw new PublishError('The photo is missing. Attach it again, then retry.');
            }
            if (!str_contains($scopes, 'media.write')) {
                throw new PublishError('Reconnect this X account to allow photo uploads, then retry. Text-only posts work without reconnecting.');
            }
            $upload = self::send('POST', self::base(self::X) . '/2/media/upload', [
                'media' => new CURLFile($file, (string) $media['mime'], 'image'),
                'media_category' => 'tweet_image',
            ], $token, timeout: 60);
            $mediaId = (string) ($upload['data']['id'] ?? ($upload['id'] ?? ($upload['media_id_string'] ?? '')));
            self::requireId($mediaId, 'X didn’t accept the photo.');
            $payload['media'] = ['media_ids' => [$mediaId]];
        }
        $sent = self::send('POST', self::base(self::X) . '/2/tweets', $payload, $token, json: true);
        $id = (string) ($sent['data']['id'] ?? '');
        self::requireId($id, 'X didn’t accept the post.');
        return ['id' => $id, 'url' => 'https://x.com/i/web/status/' . $id];
    }

    public static function pinterest(string $token, string $title, string $text, ?array $media, ?string $file): array
    {
        if ($media === null || $media['kind'] !== 'image' || $file === null) {
            throw new PublishError('Pinterest needs a photo. Attach one and schedule the Pin again.');
        }
        $boards = self::send('GET', self::base(self::PINTEREST) . '/v5/boards?' . http_build_query(['page_size' => 25]), null, $token);
        $board = '';
        foreach (is_array($boards['items'] ?? null) ? $boards['items'] : [] as $item) {
            if (is_string($item['id'] ?? null) && $item['id'] !== '') {
                $board = $item['id'];
                break;
            }
        }
        if ($board === '') {
            throw new PublishError('Create a board on Pinterest first. atwebtech pins to your first board.');
        }
        $title = trim($title) !== '' ? $title : (string) strtok(trim($text), "\n");
        $sent = self::send('POST', self::base(self::PINTEREST) . '/v5/pins', [
            'board_id' => $board,
            'title' => mb_substr(trim($title), 0, 100),
            'description' => mb_substr($text, 0, 500),
            'media_source' => ['source_type' => 'image_url', 'url' => Media::shareUrl((int) $media['id'])],
        ], $token, json: true);
        $id = (string) ($sent['id'] ?? '');
        self::requireId($id, 'Pinterest didn’t accept the Pin.');
        return ['id' => $id, 'url' => 'https://www.pinterest.com/pin/' . rawurlencode($id) . '/'];
    }

    /** Points every network host at a local fake server for tests. Ignored in production. */
    private static function base(string $url): string
    {
        $test = config('app.is_production') ? '' : rtrim((string) Env::get('NETWORK_TEST_BASE', ''), '/');
        if ($test === '') {
            return $url;
        }
        return $test . '/' . parse_url($url, PHP_URL_HOST) . (string) parse_url($url, PHP_URL_PATH);
    }

    private static function pause(int $seconds): void
    {
        if (Env::get('NETWORK_TEST_BASE', '') === '' || config('app.is_production')) {
            sleep($seconds);
        }
    }

    private static function requireId(string $id, string $message): void
    {
        if ($id === '' || strlen($id) > 191 || !preg_match('/^[A-Za-z0-9_:.-]+$/', $id)) {
            throw new PublishError($message);
        }
    }

    /** @param list<string> $hosts */
    private static function safeUrl(string $url, array $hosts): string
    {
        return str_starts_with($url, 'https://') && in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), $hosts, true) ? $url : '';
    }

    /**
     * @param array|null $fields form fields (multipart when one is a CURLFile), or a JSON body when $json is true
     * @param int $timeout seconds; 0 waits as long as the transfer takes
     */
    private static function send(string $method, string $url, ?array $fields = null, ?string $bearer = null, bool $json = false, int $timeout = 30): array
    {
        $headers = ['Accept: application/json'];
        if ($bearer !== null) {
            $headers[] = 'Authorization: Bearer ' . $bearer;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | (config('app.is_production') ? 0 : CURLPROTO_HTTP),
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($json) {
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            } else {
                $multipart = false;
                foreach ($fields ?? [] as $v) {
                    $multipart = $multipart || $v instanceof CURLFile;
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart ? $fields : http_build_query($fields ?? []));
            }
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($body === false) {
            throw new ChannelApiException('Request failed: ' . curl_error($ch), 0);
        }
        $data = json_decode((string) $body, true);
        if ($status < 200 || $status >= 300) {
            $reason = '';
            if (is_array($data)) {
                $reason = (string) ($data['error']['message'] ?? ($data['detail'] ?? ($data['message'] ?? ($data['title'] ?? ''))));
            }
            throw new ChannelApiException(sprintf('HTTP %d from %s %s', $status, parse_url($url, PHP_URL_HOST), mb_substr(strip_tags($reason), 0, 200)), $status);
        }
        return is_array($data) ? $data : [];
    }
}

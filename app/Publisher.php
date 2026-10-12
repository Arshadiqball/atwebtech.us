<?php

declare(strict_types=1);

/** Sends due posts to their connected channel. Driven by bin/publish.php from cron. */
final class Publisher
{
    public const MAX_ATTEMPTS = 3;
    private const STALE_MINUTES = 90;

    /** @return array{sent:int, failed:int, retry:int} */
    public static function run(int $batch = 5): array
    {
        $pdo = Database::connection();
        $now = Database::now();
        $pdo->prepare('UPDATE posts SET status = :planned, updated_at = :now WHERE status = :publishing AND updated_at < :stale')
            ->execute(['planned' => 'planned', 'now' => $now, 'publishing' => 'publishing', 'stale' => gmdate('Y-m-d H:i:s', time() - self::STALE_MINUTES * 60)]);

        $due = $pdo->prepare('SELECT id FROM posts WHERE status = :planned AND channel_id IS NOT NULL AND planned_at <= :now ORDER BY planned_at LIMIT ' . max(1, $batch));
        $due->execute(['planned' => 'planned', 'now' => $now]);
        $claim = $pdo->prepare('UPDATE posts SET status = :publishing, attempts = attempts + 1, updated_at = :now WHERE id = :id AND status = :planned');

        $result = ['sent' => 0, 'failed' => 0, 'retry' => 0];
        foreach ($due->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $claim->execute(['publishing' => 'publishing', 'now' => Database::now(), 'id' => (int) $id, 'planned' => 'planned']);
            if ($claim->rowCount() !== 1) {
                continue;
            }
            $result[self::publish((int) $id)]++;
        }
        return $result;
    }

    /** @return 'sent'|'failed'|'retry' */
    private static function publish(int $id): string
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM posts WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $post = $stmt->fetch();
        try {
            $channel = Channels::find((int) $post['user_id'], (int) $post['channel_id']);
            if ($channel === null) {
                throw new PublishError('The channel was disconnected.');
            }
            if (!in_array($channel['provider'], ['youtube', 'linkedin'], true)) {
                throw new PublishError('Publishing to this network isn’t built yet.');
            }
            if (!$channel['can_publish']) {
                throw new PublishError($channel['provider'] === 'linkedin'
                    ? 'Reconnect this channel and allow atwebtech to create posts on your LinkedIn Profile.'
                    : 'Reconnect this channel and allow uploads so atwebtech can publish.');
            }
            $media = $post['media_id'] ? Media::ready((int) $post['user_id'], (int) $post['media_id']) : null;
            $file = $media ? Media::file($media) : null;
            if ($channel['provider'] === 'linkedin') {
                if (trim((string) $post['body']) === '') {
                    throw new PublishError('LinkedIn posts need some text.');
                }
                if ($media !== null && $media['kind'] !== 'image') {
                    throw new PublishError('LinkedIn posts from atwebtech can include an image, not a video. Attach an image or remove the file, then retry.');
                }
                if ($media !== null && $file === null) {
                    throw new PublishError('The image file is missing. Attach it again, then retry.');
                }
                $token = Channels::accessToken($channel);
                $urn = Channels::linkedinShare($token, (string) $channel['external_id'], (string) $post['body'], $file, $media['mime'] ?? null);
                $commentError = null;
                $comment = trim((string) ($post['first_comment'] ?? ''));
                if ($comment !== '') {
                    try {
                        Channels::linkedinComment($token, (string) $channel['external_id'], $urn, $comment);
                    } catch (Throwable $e) {
                        error_log(sprintf('[%s] linkedin first comment post %d: %s', gmdate('c'), $id, $e->getMessage()));
                        $commentError = $e instanceof ChannelApiException
                            ? 'The post went out, but LinkedIn refused the first comment. You can add it on LinkedIn.'
                            : 'The post went out, but the first comment didn’t. You can add it on LinkedIn.';
                    }
                }
                $pdo->prepare('UPDATE posts SET status = :sent, sent_at = :at, external_id = :ext, external_url = :url, first_comment_error = :cerr, error = NULL, updated_at = :now WHERE id = :id')
                    ->execute(['sent' => 'sent', 'at' => Database::now(), 'ext' => $urn, 'url' => 'https://www.linkedin.com/feed/update/' . $urn, 'cerr' => $commentError, 'now' => Database::now(), 'id' => $id]);
                return 'sent';
            }
            if ($media === null || $media['kind'] !== 'video' || $file === null) {
                throw new PublishError('YouTube needs a video. Attach one and schedule the post again.');
            }
            $meta = Workspace::youtubeMeta($post);
            $videoId = Channels::uploadVideo(Channels::accessToken($channel), $file, (string) $media['mime'], [
                'snippet' => [
                    'title' => (string) $post['title'],
                    'description' => (string) $post['body'],
                    'categoryId' => $meta['category'],
                ],
                'status' => [
                    'privacyStatus' => $meta['visibility'],
                    'license' => $meta['license'],
                    'embeddable' => $meta['embeddable'],
                    'selfDeclaredMadeForKids' => $meta['kids'],
                    'containsSyntheticMedia' => $meta['synthetic'],
                ],
            ], $meta['notify']);
            $pdo->prepare('UPDATE posts SET status = :sent, sent_at = :at, external_id = :ext, external_url = :url, error = NULL, updated_at = :now WHERE id = :id')
                ->execute(['sent' => 'sent', 'at' => Database::now(), 'ext' => $videoId, 'url' => 'https://www.youtube.com/watch?v=' . $videoId, 'now' => Database::now(), 'id' => $id]);
            return 'sent';
        } catch (Throwable $e) {
            $retry = $e instanceof ChannelApiException && $e->transient() && (int) $post['attempts'] < self::MAX_ATTEMPTS;
            $li = ($channel['provider'] ?? '') === 'linkedin';
            $message = match (true) {
                $e instanceof PublishError, $e instanceof ChannelAuthException => $e->getMessage(),
                $e instanceof ChannelApiException && $e->status === 401 => ($li ? 'LinkedIn' : 'Google') . ' turned down atwebtech’s access. Reconnect the channel, then retry.',
                $e instanceof ChannelApiException && $e->status === 403 => $li ? 'LinkedIn refused the post. Check that sharing is allowed, then retry.' : 'YouTube refused the upload (daily upload quota or channel permissions). Try again later.',
                $e instanceof ChannelApiException && $e->status === 400 => $li ? 'LinkedIn rejected the post. Check the text and image, then retry.' : 'YouTube rejected the video details. Check the title and file, then retry.',
                default => $li ? 'LinkedIn didn’t accept the post. Please retry.' : 'YouTube didn’t accept the upload. Please retry.',
            };
            error_log(sprintf('[%s] publish post %d failed: %s', gmdate('c'), $id, $e->getMessage()));
            if ($retry) {
                $pdo->prepare('UPDATE posts SET status = :planned, planned_at = :next, error = :error, updated_at = :now WHERE id = :id')
                    ->execute(['planned' => 'planned', 'next' => gmdate('Y-m-d H:i:s', time() + 600 * (int) $post['attempts']), 'error' => $message, 'now' => Database::now(), 'id' => $id]);
                return 'retry';
            }
            $pdo->prepare('UPDATE posts SET status = :failed, error = :error, updated_at = :now WHERE id = :id')
                ->execute(['failed' => 'failed', 'error' => $message, 'now' => Database::now(), 'id' => $id]);
            return 'failed';
        }
    }
}

final class PublishError extends RuntimeException
{
}

<?php

declare(strict_types=1);

/**
 * Evergreen recycling. A sent post marked evergreen is copied into an empty posting slot of its channel
 * once the channel's gap (recycle_days) has passed since it last went out. Only free slots in the next
 * 3 days are filled, one copy per post at a time, and Free plan scheduling caps still apply.
 */
final class Evergreen
{
    public const GAPS = [0 => 'Off', 14 => 'Every 2 weeks', 30 => 'Every month', 60 => 'Every 2 months', 90 => 'Every 3 months'];
    private const AHEAD_DAYS = 3;
    /** A slot counts as taken when a post is planned within this many minutes of it. */
    private const SLOT_MINUTES = 45;

    /** Channels checked per run; each channel is checked at most once an hour. */
    public static function run(int $limit = 20): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT DISTINCT c.id, c.user_id FROM channels c JOIN posts p ON p.channel_id = c.id AND p.evergreen = 1 AND p.status = 'sent'
            WHERE c.recycle_days > 0 AND c.paused_at IS NULL AND (c.recycle_checked_at IS NULL OR c.recycle_checked_at < :due) LIMIT " . $limit);
        $stmt->execute(['due' => gmdate('Y-m-d H:i:s', time() - 3600)]);
        $added = 0;
        foreach ($stmt->fetchAll() as $row) {
            $pdo->prepare('UPDATE channels SET recycle_checked_at = :now WHERE id = :id')->execute(['now' => Database::now(), 'id' => (int) $row['id']]);
            $channel = Channels::find((int) $row['user_id'], (int) $row['id']);
            if ($channel !== null) {
                $added += self::fill($channel);
            }
        }
        return $added;
    }

    /** Fill this channel's free slots in the next few days. Returns how many copies were scheduled. */
    public static function fill(array $channel): int
    {
        $uid = (int) $channel['user_id'];
        $gap = (int) ($channel['recycle_days'] ?? 0);
        if ($gap <= 0 || empty($channel['can_publish']) || !$channel['times']) {
            return 0;
        }
        $pdo = Database::connection();
        $user = $pdo->prepare('SELECT timezone FROM users WHERE id = :id');
        $user->execute(['id' => $uid]);
        $tz = Workspace::timezone(['timezone' => (string) $user->fetchColumn()]);
        $utc = new DateTimeZone('UTC');
        $cap = Channels::scheduledCap($uid);

        $taken = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE channel_id = :cid AND status IN ('planned', 'review', 'publishing') AND planned_at BETWEEN :a AND :b");
        $pick = $pdo->prepare("SELECT p.* FROM posts p WHERE p.channel_id = :cid AND p.user_id = :uid AND p.evergreen = 1 AND p.status = 'sent'
            AND COALESCE(p.recycled_at, p.sent_at) < :gap
            AND NOT EXISTS (SELECT 1 FROM posts q WHERE q.recycled_from = p.id AND q.status IN ('planned', 'review', 'publishing'))
            ORDER BY COALESCE(p.recycled_at, p.sent_at) ASC LIMIT 1");
        $insert = $pdo->prepare('INSERT INTO posts (user_id, channel, channel_id, body, title, meta, tags, media_id, extra_media, first_comment, status, planned_at, recycled_from, created_at, updated_at)
            VALUES (:uid, :channel, :cid, :body, :title, :meta, :tags, :media, :extra, :first, :status, :planned, :from, :now, :now2)');
        $mark = $pdo->prepare('UPDATE posts SET recycled_at = :now WHERE id = :id');

        $now = new DateTimeImmutable('now', $tz);
        $added = 0;
        for ($d = 0; $d < self::AHEAD_DAYS; $d++) {
            $day = $now->modify('+' . $d . ' days');
            foreach ($channel['times'] as $slot) {
                if ((int) $slot['day'] !== (int) $day->format('N')) {
                    continue;
                }
                [$h, $m] = array_map('intval', explode(':', (string) $slot['time']));
                $at = $day->setTime($h, $m);
                if ($at <= $now->modify('+15 minutes')) {
                    continue;
                }
                $atUtc = $at->setTimezone($utc);
                $taken->execute([
                    'cid' => (int) $channel['id'],
                    'a' => $atUtc->modify('-' . self::SLOT_MINUTES . ' minutes')->format('Y-m-d H:i:s'),
                    'b' => $atUtc->modify('+' . self::SLOT_MINUTES . ' minutes')->format('Y-m-d H:i:s'),
                ]);
                if ((int) $taken->fetchColumn() > 0) {
                    continue;
                }
                if ($cap !== null && Channels::scheduledCount($uid, (int) $channel['id']) >= $cap) {
                    return $added;
                }
                $pick->execute(['cid' => (int) $channel['id'], 'uid' => $uid, 'gap' => gmdate('Y-m-d H:i:s', time() - $gap * 86400)]);
                $post = $pick->fetch();
                if (!$post) {
                    return $added;
                }
                $stamp = Database::now();
                $insert->execute([
                    'uid' => $uid, 'channel' => $post['channel'], 'cid' => (int) $channel['id'], 'body' => $post['body'], 'title' => $post['title'],
                    'meta' => $post['meta'], 'tags' => $post['tags'], 'media' => $post['media_id'], 'extra' => $post['extra_media'] ?? null, 'first' => $post['first_comment'],
                    'status' => 'planned', 'planned' => $atUtc->format('Y-m-d H:i:s'), 'from' => (int) $post['id'], 'now' => $stamp, 'now2' => $stamp,
                ]);
                $mark->execute(['now' => $stamp, 'id' => (int) $post['id']]);
                $added++;
            }
        }
        return $added;
    }

    /** POST /app/evergreen: mark or unmark a sent post as evergreen. */
    public static function handle(): never
    {
        $uid = (int) Auth::member()['id'];
        $id = (int) ($_POST['id'] ?? 0);
        $on = !empty($_POST['on']);
        $back = (string) ($_POST['back'] ?? '');
        $back = preg_match('~^/app/(publish|channels/\d+)(\?[A-Za-z0-9=&_-]*)?$~', $back) ? $back : '/app/publish?tab=sent';
        $stmt = Database::connection()->prepare("UPDATE posts SET evergreen = :on WHERE id = :id AND user_id = :uid AND status = 'sent'");
        $stmt->execute(['on' => $on ? 1 : 0, 'id' => $id, 'uid' => $uid]);
        if ($stmt->rowCount() === 0 && $on) {
            Toolkit::done($back, 'Only published posts can be recycled.');
        }
        if ($on) {
            $ch = Database::connection()->prepare('SELECT c.id, c.recycle_days FROM channels c JOIN posts p ON p.channel_id = c.id WHERE p.id = :id');
            $ch->execute(['id' => $id]);
            $row = $ch->fetch();
            if ($row && (int) $row['recycle_days'] === 0) {
                Toolkit::done($back, 'Marked as evergreen. Recycling is off for this channel: turn it on in the channel’s settings.');
            }
            Toolkit::done($back, 'Marked as evergreen. atwebtech will re-post it into an empty slot ' . strtolower(self::GAPS[(int) ($row['recycle_days'] ?? 30)] ?? 'later') . '.');
        }
        Toolkit::done($back, 'This post won’t be recycled.');
    }
}

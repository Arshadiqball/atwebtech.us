<?php

declare(strict_types=1);

/**
 * Media Library: every image and video the user uploaded, with where it is used. Files uploaded here
 * (or kept with "Keep in library") stay even when no post uses them; others are removed by Media::cleanup()
 * a week after their last post is deleted.
 */
final class Library
{
    public const FILTERS = ['all' => 'All', 'image' => 'Images', 'video' => 'Videos', 'unused' => 'Not in a post'];

    public static function page(int $uid): array
    {
        $filter = Workspace::pick('show', array_keys(self::FILTERS));
        $sql = "SELECT m.id, m.kind, m.mime, m.size, m.original_name, m.in_library, m.created_at,
                (SELECT COUNT(*) FROM posts p WHERE " . Media::usedBySql() . ") AS used,
                (SELECT COUNT(*) FROM posts p WHERE " . Media::usedBySql() . " AND p.status IN ('draft', 'planned', 'review', 'publishing')) AS waiting
            FROM media m WHERE m.user_id = :uid AND m.status = 'ready'";
        if ($filter === 'image' || $filter === 'video') {
            $sql .= ' AND m.kind = :kind';
        }
        if ($filter === 'unused') {
            $sql .= ' AND NOT EXISTS (SELECT 1 FROM posts p WHERE ' . Media::usedBySql() . ')';
        }
        $stmt = Database::connection()->prepare($sql . ' ORDER BY m.created_at DESC, m.id DESC LIMIT 300');
        $stmt->execute(['uid' => $uid] + (($filter === 'image' || $filter === 'video') ? ['kind' => $filter] : []));
        $usage = Database::connection()->prepare("SELECT COALESCE(SUM(expected_size), 0) FROM media WHERE user_id = :uid");
        $usage->execute(['uid' => $uid]);
        return ['items' => $stmt->fetchAll(), 'filter' => $filter, 'used' => (int) $usage->fetchColumn()];
    }

    /** POST /app/library: keep, release, rename or delete a file. */
    public static function handle(): never
    {
        $uid = (int) Auth::member()['id'];
        $show = isset($_POST['show']) && isset(self::FILTERS[$_POST['show']]) && $_POST['show'] !== 'all' ? '?show=' . $_POST['show'] : '';
        $back = '/app/library' . $show;
        $action = (string) ($_POST['action'] ?? '');
        $ids = array_slice(array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [$_POST['id'] ?? 0])))), 0, 200);
        if (!$ids) {
            Toolkit::done($back, 'Choose a file first.');
        }
        $pdo = Database::connection();
        $in = implode(',', array_fill(0, count($ids), '?'));
        if ($action === 'keep' || $action === 'release') {
            $pdo->prepare("UPDATE media SET in_library = ?, updated_at = ? WHERE user_id = ? AND status = 'ready' AND id IN ($in)")
                ->execute(array_merge([$action === 'keep' ? 1 : 0, Database::now(), $uid], $ids));
            Toolkit::done($back, $action === 'keep'
                ? (count($ids) === 1 ? 'Kept in your library.' : count($ids) . ' files kept in your library.')
                : 'Removed from the library. The file is deleted a week after no post uses it.');
        }
        if ($action === 'rename') {
            $name = mb_substr(trim(str_replace(["\0", '/', '\\'], '', (string) ($_POST['name'] ?? ''))), 0, 190);
            if ($name === '') {
                Toolkit::done($back, 'Give the file a name.');
            }
            $pdo->prepare('UPDATE media SET original_name = :n WHERE id = :id AND user_id = :uid')->execute(['n' => $name, 'id' => $ids[0], 'uid' => $uid]);
            Toolkit::done($back, 'Renamed.');
        }
        if ($action === 'delete') {
            $stmt = $pdo->prepare("SELECT m.*, (SELECT COUNT(*) FROM posts p WHERE " . Media::usedBySql() . " AND (p.status IN ('draft', 'planned', 'review', 'publishing') OR p.evergreen = 1)) AS busy
                FROM media m WHERE m.user_id = ? AND m.id IN ($in)");
            $stmt->execute(array_merge([$uid], $ids));
            $deleted = 0;
            $kept = 0;
            foreach ($stmt->fetchAll() as $media) {
                if ((int) $media['busy'] > 0) {
                    $kept++;
                    continue;
                }
                $pdo->prepare('UPDATE posts SET media_id = NULL WHERE media_id = :id AND user_id = :uid')->execute(['id' => (int) $media['id'], 'uid' => $uid]);
                $pdo->prepare("UPDATE posts SET extra_media = REPLACE(extra_media, :needle, ',') WHERE user_id = :uid AND extra_media LIKE :like")->execute(['needle' => ',' . (int) $media['id'] . ',', 'uid' => $uid, 'like' => '%,' . (int) $media['id'] . ',%']);
                Media::discard($media);
                $deleted++;
            }
            Toolkit::done($back, ($deleted > 0 ? $deleted . ' ' . ($deleted === 1 ? 'file' : 'files') . ' deleted.' : 'Nothing was deleted.')
                . ($kept > 0 ? ' ' . $kept . ' ' . ($kept === 1 ? 'file is' : 'files are') . ' used by a draft, scheduled or evergreen post, so ' . ($kept === 1 ? 'it was' : 'they were') . ' kept.' : ''));
        }
        Toolkit::done($back, 'Nothing changed.');
    }
}

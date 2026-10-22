<?php

declare(strict_types=1);

/**
 * Private media uploads. Files arrive in chunks (so large videos fit under the default post_max_size),
 * live outside the web root in storage/media/{user}/ and are only served to their owner.
 */
final class Media
{
    public const CHUNK = 4 * 1024 * 1024;
    public const TYPES = [
        'image/jpeg' => ['image', 'jpg'],
        'image/png' => ['image', 'png'],
        'image/webp' => ['image', 'webp'],
        'image/gif' => ['image', 'gif'],
        'video/mp4' => ['video', 'mp4'],
        'video/quicktime' => ['video', 'mov'],
        'video/webm' => ['video', 'webm'],
    ];
    public const MAX = ['image' => 10 * 1024 * 1024, 'video' => 200 * 1024 * 1024];
    private const QUOTA = 2 * 1024 * 1024 * 1024;

    public static function start(): never
    {
        $uid = (int) Auth::member()['id'];
        $name = mb_substr(trim(str_replace(["\0", '/', '\\'], '', (string) ($_POST['name'] ?? ''))), 0, 190) ?: 'upload';
        $size = (int) ($_POST['size'] ?? 0);
        $type = strtolower((string) ($_POST['type'] ?? ''));
        if (!isset(self::TYPES[$type])) {
            self::fail('Upload a JPG, PNG, WebP or GIF image, or an MP4, MOV or WebM video.', 415);
        }
        $kind = self::TYPES[$type][0];
        if ($size < 1 || $size > self::MAX[$kind]) {
            self::fail($kind === 'image' ? 'Images can be up to 10 MB.' : 'Videos can be up to 200 MB.', 413);
        }
        $fp = 'user:' . $uid;
        if (RateLimiter::tooManyAttempts('media-upload', $fp, 40, 60)) {
            self::fail('That’s a lot of uploads. Please wait a little and try again.', 429);
        }
        if (self::usage($uid) + $size > self::QUOTA) {
            self::fail('Your media storage is full (2 GB). Delete or publish some posts and try again.', 413);
        }
        RateLimiter::hit('media-upload', $fp);

        $relative = $uid . '/' . bin2hex(random_bytes(16)) . '.part';
        $dir = self::root() . '/' . $uid;
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            self::fail('Uploads aren’t available right now.', 500);
        }
        touch(self::root() . '/' . $relative);
        $now = Database::now();
        $pdo = Database::connection();
        $pdo->prepare('INSERT INTO media (user_id, kind, mime, size, expected_size, original_name, path, status, created_at, updated_at) VALUES (:uid, :kind, NULL, 0, :expected, :name, :path, :status, :now, :now2)')
            ->execute(['uid' => $uid, 'kind' => $kind, 'expected' => $size, 'name' => $name, 'path' => $relative, 'status' => 'uploading', 'now' => $now, 'now2' => $now]);
        json_response(['status' => 'ok', 'id' => (int) $pdo->lastInsertId(), 'chunk' => self::CHUNK]);
    }

    public static function chunk(string $id): never
    {
        $uid = (int) Auth::member()['id'];
        $media = self::find($uid, (int) $id);
        if ($media === null || $media['status'] !== 'uploading') {
            self::fail('That upload isn’t open any more. Please choose the file again.', 404);
        }
        $file = self::root() . '/' . $media['path'];
        clearstatcache(true, $file);
        $current = (int) @filesize($file);
        if ((int) ($_SERVER['HTTP_X_UPLOAD_OFFSET'] ?? -1) !== $current) {
            json_response(['status' => 'error', 'message' => 'Upload got out of step.', 'offset' => $current], 409);
        }
        $in = fopen('php://input', 'rb');
        $out = fopen($file, 'ab');
        $written = stream_copy_to_stream($in, $out, self::CHUNK + 1);
        fclose($in);
        fclose($out);
        $total = $current + (int) $written;
        if ($written === false || $written > self::CHUNK || $total > (int) $media['expected_size']) {
            self::discard($media);
            self::fail('The file is bigger than it said it was. Please try again.', 413);
        }
        Database::connection()->prepare('UPDATE media SET size = :size, updated_at = :now WHERE id = :id')
            ->execute(['size' => $total, 'now' => Database::now(), 'id' => (int) $media['id']]);
        json_response(['status' => 'ok', 'offset' => $total]);
    }

    public static function complete(string $id): never
    {
        $uid = (int) Auth::member()['id'];
        $media = self::find($uid, (int) $id);
        if ($media === null || $media['status'] !== 'uploading') {
            self::fail('That upload isn’t open any more. Please choose the file again.', 404);
        }
        $file = self::root() . '/' . $media['path'];
        clearstatcache(true, $file);
        if ((int) @filesize($file) !== (int) $media['expected_size']) {
            self::discard($media);
            self::fail('The upload didn’t finish. Please try again.', 422);
        }
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($file);
        $type = self::TYPES[$mime] ?? null;
        if ($type === null || $type[0] !== $media['kind'] || ($type[0] === 'image' && @getimagesize($file) === false)) {
            self::discard($media);
            self::fail('That file isn’t a supported image or video.', 415);
        }
        $final = preg_replace('/\.part$/', '.' . $type[1], $media['path']);
        if (!rename($file, self::root() . '/' . $final)) {
            self::fail('Uploads aren’t available right now.', 500);
        }
        Database::connection()->prepare('UPDATE media SET mime = :mime, path = :path, status = :status, updated_at = :now WHERE id = :id')
            ->execute(['mime' => $mime, 'path' => $final, 'status' => 'ready', 'now' => Database::now(), 'id' => (int) $media['id']]);
        json_response(['status' => 'ok', 'media' => self::describe(self::find($uid, (int) $media['id']))]);
    }

    public static function serve(string $id): never
    {
        $user = Auth::require();
        $media = self::find((int) $user['id'], (int) $id);
        $file = $media && $media['status'] === 'ready' ? self::file($media) : null;
        if ($file === null) {
            not_found();
        }
        session_write_close();
        $size = (int) filesize($file);
        $start = 0;
        $end = $size - 1;
        if (preg_match('/^bytes=(\d*)-(\d*)$/', (string) ($_SERVER['HTTP_RANGE'] ?? ''), $m) && ($m[1] !== '' || $m[2] !== '')) {
            if ($m[1] === '') {
                $start = max(0, $size - (int) $m[2]);
            } else {
                $start = (int) $m[1];
                $end = $m[2] === '' ? $end : min($end, (int) $m[2]);
            }
            if ($start > $end || $start >= $size) {
                http_response_code(416);
                header('Content-Range: bytes */' . $size);
                exit;
            }
            http_response_code(206);
            header(sprintf('Content-Range: bytes %d-%d/%d', $start, $end, $size));
        }
        header('Content-Type: ' . $media['mime']);
        header('Content-Length: ' . ($end - $start + 1));
        header('Accept-Ranges: bytes');
        header('Cache-Control: private, max-age=3600');
        header('Content-Disposition: inline; filename="media-' . (int) $media['id'] . '.' . self::TYPES[$media['mime']][1] . '"');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        $handle = fopen($file, 'rb');
        fseek($handle, $start);
        $left = $end - $start + 1;
        while ($left > 0 && !feof($handle)) {
            $buffer = fread($handle, min(1024 * 256, $left));
            echo $buffer;
            $left -= strlen($buffer);
            flush();
        }
        fclose($handle);
        exit;
    }

    public static function find(int $uid, int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM media WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $id, 'uid' => $uid]);
        return $stmt->fetch() ?: null;
    }

    /** A ready upload the user owns, or null. */
    public static function ready(int $uid, int $id): ?array
    {
        $media = $id > 0 ? self::find($uid, $id) : null;
        return $media && $media['status'] === 'ready' ? $media : null;
    }

    public static function file(array $media): ?string
    {
        $path = self::root() . '/' . $media['path'];
        return is_file($path) ? $path : null;
    }

    /** A short-lived public JPEG address Instagram can fetch while publishing. */
    public static function shareUrl(int $id): string
    {
        $exp = time() + 7200;
        $sig = Security::keyedHash($id . '.' . $exp);
        $origin = public_origin();
        if (parse_url($origin, PHP_URL_HOST) !== 'atwebtech.us') {
            $origin = 'https://atwebtech.us';
        }
        return $origin . '/media/ig/' . $id . '/' . $exp . '/' . $sig;
    }

    /** Serve one ready image as a JPEG when the signed address is still valid. */
    public static function publicJpeg(string $id, string $exp, string $sig): never
    {
        $valid = ctype_digit($id) && ctype_digit($exp) && ctype_xdigit($sig) && strlen($sig) === 64
            && hash_equals(Security::keyedHash($id . '.' . $exp), $sig)
            && (int) $exp >= time()
            && (int) $exp <= time() + 86400;
        $media = null;
        if ($valid) {
            $stmt = Database::connection()->prepare('SELECT * FROM media WHERE id = :id AND status = :status AND kind = :kind');
            $stmt->execute(['id' => (int) $id, 'status' => 'ready', 'kind' => 'image']);
            $media = $stmt->fetch() ?: null;
        }
        $file = $media ? self::file($media) : null;
        $jpeg = $file ? self::jpegBytes($file) : null;
        if ($jpeg === null) {
            not_found();
        }
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . strlen($jpeg));
        header('Cache-Control: private, max-age=300');
        header('X-Robots-Tag: noindex');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        echo $jpeg;
        exit;
    }

    /** Instagram feed photos are JPEG, between 4:5 and 1.91:1, and at most 8 MB. */
    private static function jpegBytes(string $path): ?string
    {
        $info = @getimagesize($path);
        if ($info === false) {
            return null;
        }
        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            default => false,
        };
        if ($src === false) {
            return null;
        }
        $width = imagesx($src);
        $height = imagesy($src);
        if ($width < 1 || $height < 1) {
            return null;
        }
        $ratio = $width / $height;
        $target = min(1.91, max(0.8, $ratio));
        if ($ratio > $target) {
            $cropW = (int) round($height * $target);
            $cropH = $height;
            $cropX = (int) (($width - $cropW) / 2);
            $cropY = 0;
        } else {
            $cropW = $width;
            $cropH = (int) round($width / $target);
            $cropX = 0;
            $cropY = (int) (($height - $cropH) / 2);
        }
        $cropW = min($width, max(1, $cropW));
        $cropH = min($height, max(1, $cropH));
        $cropX = min($width - $cropW, max(0, $cropX));
        $cropY = min($height - $cropH, max(0, $cropY));
        $outW = min(1440, max(320, $cropW));
        $outH = max(1, (int) round($outW / $target));
        $dst = imagecreatetruecolor($outW, $outH);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, $outW, $outH, $cropW, $cropH);
        $quality = 86;
        $bytes = '';
        do {
            ob_start();
            imagejpeg($dst, null, $quality);
            $bytes = (string) ob_get_clean();
            $quality -= 12;
        } while (strlen($bytes) > 8 * 1024 * 1024 && $quality >= 50);
        return $bytes !== '' && strlen($bytes) <= 8 * 1024 * 1024 ? $bytes : null;
    }

    /** Copy a template image or video into this member’s private media so a scheduled post can use it. */
    /** @return array{id:int, file:string} */
    public static function copyReady(int $uid, string $absolute, string $name): array
    {
        if (!is_file($absolute)) {
            throw new RuntimeException('That template file is missing.');
        }
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($absolute);
        $type = self::TYPES[$mime] ?? null;
        if ($type === null || ($type[0] === 'image' && @getimagesize($absolute) === false)) {
            throw new RuntimeException('That template file is not a supported image or video.');
        }
        $size = filesize($absolute);
        if ($size === false || $size < 1 || $size > self::MAX[$type[0]]) {
            throw new RuntimeException($type[0] === 'video' ? 'That template video is too large.' : 'That template image is too large.');
        }
        if (self::usage($uid) + $size > self::QUOTA) {
            throw new RuntimeException('Your media storage is full (2 GB). Delete or publish some posts and try again.');
        }
        $dir = self::root() . '/' . $uid;
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Uploads aren’t available right now.');
        }
        $relative = $uid . '/' . bin2hex(random_bytes(16)) . '.' . $type[1];
        if (!copy($absolute, self::root() . '/' . $relative)) {
            throw new RuntimeException('Uploads aren’t available right now.');
        }
        $now = Database::now();
        $pdo = Database::connection();
        $pdo->prepare('INSERT INTO media (user_id, kind, mime, size, expected_size, original_name, path, status, created_at, updated_at) VALUES (:uid, :kind, :mime, :size, :expected, :name, :path, :status, :now, :now2)')
            ->execute([
                'uid' => $uid,
                'kind' => $type[0],
                'mime' => $mime,
                'size' => $size,
                'expected' => $size,
                'name' => mb_substr($name, 0, 190),
                'path' => $relative,
                'status' => 'ready',
                'now' => $now,
                'now2' => $now,
            ]);
        return ['id' => (int) $pdo->lastInsertId(), 'file' => self::root() . '/' . $relative];
    }

    public static function describe(?array $media): ?array
    {
        if ($media === null) {
            return null;
        }
        return ['id' => (int) $media['id'], 'kind' => $media['kind'], 'name' => $media['original_name'], 'size' => (int) $media['size'], 'url' => '/app/media/' . (int) $media['id']];
    }

    /** Delete abandoned uploads and media no post uses any more. Run from the publishing worker. */
    public static function cleanup(): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT m.* FROM media m WHERE (m.status = :uploading AND m.updated_at < :day) OR (m.status = :ready AND m.updated_at < :week AND NOT EXISTS (SELECT 1 FROM posts p WHERE p.media_id = m.id))');
        $stmt->execute(['uploading' => 'uploading', 'day' => gmdate('Y-m-d H:i:s', time() - 86400), 'ready' => 'ready', 'week' => gmdate('Y-m-d H:i:s', time() - 7 * 86400)]);
        $rows = $stmt->fetchAll();
        foreach ($rows as $media) {
            self::discard($media);
        }
        return count($rows);
    }

    private static function discard(array $media): void
    {
        @unlink(self::root() . '/' . $media['path']);
        Database::connection()->prepare('DELETE FROM media WHERE id = :id')->execute(['id' => (int) $media['id']]);
    }

    private static function usage(int $uid): int
    {
        $stmt = Database::connection()->prepare('SELECT COALESCE(SUM(expected_size), 0) FROM media WHERE user_id = :uid');
        $stmt->execute(['uid' => $uid]);
        return (int) $stmt->fetchColumn();
    }

    private static function root(): string
    {
        return BASE_PATH . '/storage/media';
    }

    private static function fail(string $message, int $status): never
    {
        json_response(['status' => 'error', 'message' => $message], $status);
    }
}

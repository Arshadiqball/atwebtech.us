<?php

declare(strict_types=1);

/** Byte-mode QR code, error correction M, rendered as an SVG. */
final class QrCode
{
    private const EC = [
        1 => 10, 2 => 16, 3 => 26, 4 => 18, 5 => 24, 6 => 16, 7 => 18, 8 => 22, 9 => 22, 10 => 26,
        11 => 30, 12 => 22, 13 => 22, 14 => 24, 15 => 24, 16 => 28, 17 => 28, 18 => 26, 19 => 26, 20 => 26,
    ];
    private const BLOCKS = [
        1 => 1, 2 => 1, 3 => 1, 4 => 2, 5 => 2, 6 => 4, 7 => 4, 8 => 4, 9 => 5, 10 => 5,
        11 => 5, 12 => 8, 13 => 9, 14 => 9, 15 => 10, 16 => 10, 17 => 11, 18 => 13, 19 => 14, 20 => 16,
    ];

    public static function svg(string $text): string
    {
        $modules = self::matrix($text);
        $n = count($modules);
        $quiet = 4;
        $size = $n + $quiet * 2;
        $path = '';
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($modules[$y][$x]) {
                    $path .= 'M' . ($x + $quiet) . ' ' . ($y + $quiet) . 'h1v1h-1z';
                }
            }
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $size . ' ' . $size . '" shape-rendering="crispEdges" role="img" aria-label="QR code for your authenticator app">'
            . '<rect width="100%" height="100%" fill="#fff"/>'
            . '<path fill="#111" d="' . $path . '"/>'
            . '</svg>';
    }

    /** @return list<list<bool>> */
    public static function matrix(string $text): array
    {
        if ($text === '' || strlen($text) > 800) {
            throw new RuntimeException('QR payload is empty or too long.');
        }
        $version = self::version($text);
        $size = $version * 4 + 17;
        $modules = array_fill(0, $size, array_fill(0, $size, false));
        $function = array_fill(0, $size, array_fill(0, $size, false));

        self::finder($modules, $function, 0, 0);
        self::finder($modules, $function, $size - 7, 0);
        self::finder($modules, $function, 0, $size - 7);
        for ($i = 0; $i < $size; $i++) {
            if (!$function[6][$i]) {
                $modules[6][$i] = $i % 2 === 0;
                $function[6][$i] = true;
            }
            if (!$function[$i][6]) {
                $modules[$i][6] = $i % 2 === 0;
                $function[$i][6] = true;
            }
        }
        foreach (self::alignmentCenters($version) as $cy) {
            foreach (self::alignmentCenters($version) as $cx) {
                if (!self::overlapsFinder($cx, $cy, $size)) {
                    self::alignment($modules, $function, $cx, $cy);
                }
            }
        }
        $modules[$size - 8][8] = true;
        $function[$size - 8][8] = true;
        foreach (self::formatCoords($size) as [$x, $y]) {
            $function[$y][$x] = true;
        }
        if ($version >= 7) {
            foreach (self::versionCoords($size) as [$x, $y]) {
                $function[$y][$x] = true;
            }
            self::paintVersion($modules, $version);
        }

        $raw = self::rawModules($version);
        $free = 0;
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if (!$function[$y][$x]) {
                    $free++;
                }
            }
        }
        if ($free !== $raw) {
            throw new RuntimeException('QR function patterns do not match this version.');
        }

        $total = intdiv($raw, 8);
        $dataBytes = $total - self::EC[$version] * self::BLOCKS[$version];
        $codewords = self::codewords($text, $version, $dataBytes);
        self::placeData($modules, $function, $codewords);

        $best = null;
        $bestScore = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $candidate = $modules;
            for ($y = 0; $y < $size; $y++) {
                for ($x = 0; $x < $size; $x++) {
                    if (!$function[$y][$x] && self::maskBit($mask, $x, $y)) {
                        $candidate[$y][$x] = !$candidate[$y][$x];
                    }
                }
            }
            self::paintFormat($candidate, $size, $mask);
            $score = self::penalty($candidate);
            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }
        return $best;
    }

    private static function version(string $text): int
    {
        $length = strlen($text);
        for ($version = 1; $version <= 20; $version++) {
            $dataBytes = intdiv(self::rawModules($version), 8) - self::EC[$version] * self::BLOCKS[$version];
            $header = 4 + ($version <= 9 ? 8 : 16);
            if ($header + $length * 8 <= $dataBytes * 8) {
                return $version;
            }
        }
        throw new RuntimeException('QR payload is too long.');
    }

    /** @return list<int> */
    private static function codewords(string $text, int $version, int $dataBytes): array
    {
        $bits = array_merge(self::bits(0b0100, 4), self::bits(strlen($text), $version <= 9 ? 8 : 16));
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $bits = array_merge($bits, self::bits(ord($text[$i]), 8));
        }
        $capacity = $dataBytes * 8;
        $terminator = min(4, $capacity - count($bits));
        if ($terminator < 0) {
            throw new RuntimeException('QR payload is too long.');
        }
        for ($i = 0; $i < $terminator; $i++) {
            $bits[] = 0;
        }
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }
        $pad = [0xEC, 0x11];
        $p = 0;
        while (count($bits) < $capacity) {
            foreach (self::bits($pad[$p % 2], 8) as $bit) {
                $bits[] = $bit;
            }
            $p++;
        }
        $bytes = [];
        for ($i = 0; $i < $dataBytes; $i++) {
            $value = 0;
            for ($b = 0; $b < 8; $b++) {
                $value = ($value << 1) | $bits[$i * 8 + $b];
            }
            $bytes[] = $value;
        }

        $ec = self::EC[$version];
        $blocks = self::BLOCKS[$version];
        $shortCount = $blocks - ($dataBytes % $blocks);
        $shortLen = intdiv($dataBytes, $blocks);
        $groups = [];
        $offset = 0;
        for ($i = 0; $i < $blocks; $i++) {
            $len = $shortLen + ($i >= $shortCount ? 1 : 0);
            $data = array_slice($bytes, $offset, $len);
            $offset += $len;
            $groups[] = ['data' => $data, 'ec' => self::remainder($data, $ec)];
        }
        $out = [];
        $max = $shortLen + 1;
        for ($i = 0; $i < $max; $i++) {
            foreach ($groups as $group) {
                if ($i < count($group['data'])) {
                    $out[] = $group['data'][$i];
                }
            }
        }
        for ($i = 0; $i < $ec; $i++) {
            foreach ($groups as $group) {
                $out[] = $group['ec'][$i];
            }
        }
        return $out;
    }

    /** @param list<list<bool>> $modules @param list<list<bool>> $function @param list<int> $codewords */
    private static function placeData(array &$modules, array $function, array $codewords): void
    {
        $size = count($modules);
        $i = 0;
        $total = count($codewords) * 8;
        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $size - 1 - $vert : $vert;
                    if (!$function[$y][$x] && $i < $total) {
                        $modules[$y][$x] = (($codewords[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }
        if ($i !== $total) {
            throw new RuntimeException('QR data did not fill the matrix.');
        }
    }

    /** @param list<list<bool>> $modules @param list<list<bool>> $function */
    private static function finder(array &$modules, array &$function, int $row, int $col): void
    {
        $size = count($modules);
        for ($y = -1; $y <= 7; $y++) {
            for ($x = -1; $x <= 7; $x++) {
                $yy = $row + $y;
                $xx = $col + $x;
                if ($yy < 0 || $xx < 0 || $yy >= $size || $xx >= $size) {
                    continue;
                }
                $inside = $x >= 0 && $x <= 6 && $y >= 0 && $y <= 6;
                $modules[$yy][$xx] = $inside && ($x === 0 || $x === 6 || $y === 0 || $y === 6 || ($x >= 2 && $x <= 4 && $y >= 2 && $y <= 4));
                $function[$yy][$xx] = true;
            }
        }
    }

    /** @param list<list<bool>> $modules @param list<list<bool>> $function */
    private static function alignment(array &$modules, array &$function, int $cx, int $cy): void
    {
        for ($y = -2; $y <= 2; $y++) {
            for ($x = -2; $x <= 2; $x++) {
                $modules[$cy + $y][$cx + $x] = max(abs($x), abs($y)) !== 1;
                $function[$cy + $y][$cx + $x] = true;
            }
        }
    }

    private static function overlapsFinder(int $cx, int $cy, int $size): bool
    {
        foreach ([[0, 0], [$size - 7, 0], [0, $size - 7]] as [$col, $row]) {
            $ax1 = $cx - 2;
            $ax2 = $cx + 2;
            $ay1 = $cy - 2;
            $ay2 = $cy + 2;
            if ($ax1 <= $col + 7 && $ax2 >= $col - 1 && $ay1 <= $row + 7 && $ay2 >= $row - 1) {
                return true;
            }
        }
        return false;
    }

    /** @return list<int> */
    private static function alignmentCenters(int $version): array
    {
        if ($version === 1) {
            return [];
        }
        $count = intdiv($version, 7) + 2;
        $step = intdiv($version * 4 + $count * 2 + 1, $count * 2 - 2) * 2;
        $positions = array_fill(0, $count, 0);
        $positions[0] = 6;
        for ($i = $count - 1, $pos = $version * 4 + 10; $i >= 1; $i--, $pos -= $step) {
            $positions[$i] = $pos;
        }
        return $positions;
    }

    /** @param list<list<bool>> $modules */
    private static function paintFormat(array &$modules, int $size, int $mask): void
    {
        $bits = self::formatBits($mask);
        foreach (self::formatCoords($size) as $i => [$x, $y]) {
            $modules[$y][$x] = (($bits >> ($i % 15)) & 1) === 1;
        }
    }

    /** @return list<array{0:int,1:int}> column, row. Bit 0 is the least significant bit. */
    private static function formatCoords(int $size): array
    {
        $coords = [];
        for ($i = 0; $i <= 5; $i++) {
            $coords[] = [8, $i];
        }
        $coords[] = [8, 7];
        $coords[] = [8, 8];
        $coords[] = [7, 8];
        for ($i = 9; $i < 15; $i++) {
            $coords[] = [14 - $i, 8];
        }
        for ($i = 0; $i < 8; $i++) {
            $coords[] = [$size - 1 - $i, 8];
        }
        for ($i = 8; $i < 15; $i++) {
            $coords[] = [8, $size - 15 + $i];
        }
        return $coords;
    }

    /** @param list<list<bool>> $modules */
    private static function paintVersion(array &$modules, int $version): void
    {
        $bits = self::versionBits($version);
        $size = count($modules);
        for ($i = 0; $i < 18; $i++) {
            $a = $size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $on = (($bits >> $i) & 1) === 1;
            $modules[$b][$a] = $on;
            $modules[$a][$b] = $on;
        }
    }

    /** @return list<array{0:int,1:int}> */
    private static function versionCoords(int $size): array
    {
        $coords = [];
        for ($i = 0; $i < 18; $i++) {
            $a = $size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $coords[] = [$a, $b];
            $coords[] = [$b, $a];
        }
        return $coords;
    }

    private static function formatBits(int $mask): int
    {
        $data = $mask & 7;
        $rem = $data << 10;
        $poly = 0b10100110111;
        for ($i = 14; $i >= 10; $i--) {
            if (($rem & (1 << $i)) !== 0) {
                $rem ^= $poly << ($i - 10);
            }
        }
        return (($data << 10) | ($rem & 0x3FF)) ^ 0x5412;
    }

    private static function versionBits(int $version): int
    {
        $rem = $version << 12;
        $poly = 0x1F25;
        for ($i = 17; $i >= 12; $i--) {
            if (($rem & (1 << $i)) !== 0) {
                $rem ^= $poly << ($i - 12);
            }
        }
        return ($version << 12) | ($rem & 0xFFF);
    }

    /** @param list<list<bool>> $modules */
    private static function penalty(array $modules): int
    {
        $n = count($modules);
        $score = 0;
        foreach ([false, true] as $column) {
            for ($i = 0; $i < $n; $i++) {
                $run = 1;
                $prev = null;
                $line = '';
                for ($j = 0; $j < $n; $j++) {
                    $bit = $column ? $modules[$j][$i] : $modules[$i][$j];
                    $line .= $bit ? '1' : '0';
                    if ($bit === $prev) {
                        $run++;
                        if ($run === 5) {
                            $score += 3;
                        } elseif ($run > 5) {
                            $score++;
                        }
                    } else {
                        $run = 1;
                        $prev = $bit;
                    }
                }
                foreach (['10111010000', '00001011101'] as $pattern) {
                    $offset = 0;
                    while (($pos = strpos($line, $pattern, $offset)) !== false) {
                        $score += 40;
                        $offset = $pos + 1;
                    }
                }
            }
        }
        for ($y = 0; $y < $n - 1; $y++) {
            for ($x = 0; $x < $n - 1; $x++) {
                $v = $modules[$y][$x];
                if ($v === $modules[$y][$x + 1] && $v === $modules[$y + 1][$x] && $v === $modules[$y + 1][$x + 1]) {
                    $score += 3;
                }
            }
        }
        $dark = 0;
        foreach ($modules as $row) {
            foreach ($row as $bit) {
                if ($bit) {
                    $dark++;
                }
            }
        }
        return $score + intdiv(abs((int) (100 * $dark / ($n * $n)) - 50), 5) * 10;
    }

    private static function maskBit(int $mask, int $x, int $y): bool
    {
        return match ($mask) {
            0 => ($x + $y) % 2 === 0,
            1 => $y % 2 === 0,
            2 => $x % 3 === 0,
            3 => ($x + $y) % 3 === 0,
            4 => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
            5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
            6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
            default => (($x + $y) % 2 + ($x * $y) % 3) % 2 === 0,
        };
    }

    private static function rawModules(int $version): int
    {
        $result = (16 * $version + 128) * $version + 64;
        if ($version >= 2) {
            $align = intdiv($version, 7) + 2;
            $result -= (25 * $align - 10) * $align - 55;
            if ($version >= 7) {
                $result -= 36;
            }
        }
        return $result;
    }

    /** @param list<int> $data @return list<int> */
    private static function remainder(array $data, int $ec): array
    {
        [$exp, $log] = self::field();
        $gen = self::generator($ec, $exp, $log);
        $msg = array_merge($data, array_fill(0, $ec, 0));
        $dataLen = count($data);
        $genLen = count($gen);
        for ($i = 0; $i < $dataLen; $i++) {
            $factor = $msg[$i];
            if ($factor === 0) {
                continue;
            }
            for ($j = 0; $j < $genLen; $j++) {
                $msg[$i + $j] ^= self::mul($gen[$j], $factor, $exp, $log);
            }
        }
        return array_slice($msg, $dataLen);
    }

    /** @param list<int> $exp @param list<int> $log @return list<int> */
    private static function generator(int $ec, array $exp, array $log): array
    {
        static $cache = [];
        if (isset($cache[$ec])) {
            return $cache[$ec];
        }
        $gen = [1];
        for ($i = 0; $i < $ec; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            foreach ($gen as $j => $coef) {
                $next[$j] ^= $coef;
                $next[$j + 1] ^= self::mul($coef, $exp[$i], $exp, $log);
            }
            $gen = $next;
        }
        return $cache[$ec] = $gen;
    }

    /** @return array{0:list<int>,1:list<int>} */
    private static function field(): array
    {
        static $exp, $log;
        if ($exp !== null) {
            return [$exp, $log];
        }
        $exp = array_fill(0, 512, 0);
        $log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if (($x & 0x100) !== 0) {
                $x ^= 0x11D;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            $exp[$i] = $exp[$i - 255];
        }
        return [$exp, $log];
    }

    /** @param list<int> $exp @param list<int> $log */
    private static function mul(int $a, int $b, array $exp, array $log): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return $exp[$log[$a] + $log[$b]];
    }

    /** @return list<int> */
    private static function bits(int $value, int $length): array
    {
        $out = [];
        for ($i = $length - 1; $i >= 0; $i--) {
            $out[] = ($value >> $i) & 1;
        }
        return $out;
    }
}

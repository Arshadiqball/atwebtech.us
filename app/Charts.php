<?php

declare(strict_types=1);

final class Charts
{
    /** @return array{line:string, area:string, points:array<int,array{0:float,1:float}>} */
    public static function line(array $values, float $width, float $height, float $pad = 4): array
    {
        $min = min($values);
        $max = max($values);
        $range = max(1, $max - $min);
        $count = count($values);
        $points = [];
        foreach (array_values($values) as $i => $v) {
            $x = $count > 1 ? $pad + ($width - 2 * $pad) * $i / ($count - 1) : $width / 2;
            $y = $height - $pad - ($height - 2 * $pad) * ($v - $min) / $range;
            $points[] = [round($x, 2), round($y, 2)];
        }
        $line = '';
        foreach ($points as $i => [$x, $y]) {
            $line .= ($i === 0 ? 'M' : 'L') . $x . ' ' . $y . ' ';
        }
        $last = end($points);
        $area = $line . 'L' . $last[0] . ' ' . $height . ' L' . $points[0][0] . ' ' . $height . ' Z';
        return ['line' => trim($line), 'area' => $area, 'points' => $points];
    }

    /** @return array<int,array{x:float,y:float,w:float,h:float,v:int}> */
    public static function bars(array $values, float $width, float $height, float $gap = 4): array
    {
        $max = max(1, max($values));
        $count = count($values);
        $barWidth = ($width - $gap * ($count - 1)) / $count;
        $out = [];
        foreach (array_values($values) as $i => $v) {
            $h = max(2, $height * $v / $max);
            $out[] = ['x' => round($i * ($barWidth + $gap), 2), 'y' => round($height - $h, 2), 'w' => round($barWidth, 2), 'h' => round($h, 2), 'v' => $v];
        }
        return $out;
    }
}

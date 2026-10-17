<?php

namespace App\Support;

class SignatureImage
{
    public static function source(?string $value): ?string
    {
        $value = trim((string) $value);
        if (str_starts_with($value, 'SIG_VECTOR_V1|')) {
            return self::vector($value);
        }
        if (preg_match('/^data:image\/(?:png|jpe?g|webp);base64,[A-Za-z0-9+\/=\s]+$/i', $value)) {
            return preg_replace('/\s+/', '', $value);
        }

        return $value !== '' && preg_match('/^[A-Za-z0-9+\/=\s]+$/', $value)
            ? 'data:image/png;base64,'.preg_replace('/\s+/', '', $value) : null;
    }

    private static function vector(string $value): ?string
    {
        if (strlen($value) > 500000 || ! preg_match('/^SIG_VECTOR_V1\|(\d+)x(\d+)\|(.+)$/D', $value, $matches)) {
            return null;
        }
        $width = (int) $matches[1];
        $height = (int) $matches[2];
        if ($width < 1 || $height < 1 || $width > 16384 || $height > 16384) {
            return null;
        }
        $paths = [];
        foreach (explode(';_;', $matches[3]) as $stroke) {
            $points = [];
            foreach (explode(';', $stroke) as $point) {
                $xy = explode(',', $point);
                if (count($xy) !== 2 || ! is_numeric($xy[0]) || ! is_numeric($xy[1])) {
                    return null;
                }
                [$x, $y] = array_map('floatval', $xy);
                if (! is_finite($x) || ! is_finite($y) || $x < -$width || $x > $width * 2 || $y < -$height || $y > $height * 2) {
                    return null;
                }
                $points[] = $x.','.$y;
            }
            $paths[] = count($points) === 1
                ? '<circle cx="'.$x.'" cy="'.$y.'" r="1.5" />'
                : '<polyline points="'.implode(' ', $points).'" fill="none" />';
        }
        // Only generated numeric geometry enters SVG; stored markup is never rendered.
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$width.' '.$height.'"><g stroke="#17212f" fill="#17212f" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">'.implode('', $paths).'</g></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}

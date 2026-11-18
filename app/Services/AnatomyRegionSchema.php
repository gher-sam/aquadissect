<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class AnatomyRegionSchema
{
    public static function validate(array $anatomy, array $manifest, string $root): void
    {
        $maps = $anatomy['regionMaps'] ?? [];
        if (! is_array($maps) || array_diff(array_keys($maps), ['external', 'internal'])) {
            self::fail('Region maps must be external/internal image-bound objects.');
        }
        $parts = collect($anatomy['structures'])->keyBy('id');
        $mapped = 0;
        $vertices = 0;
        foreach ($maps as $layer => $map) {
            $path = $manifest['assets'][$layer];
            $size = getimagesize($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path));
            if (! is_array($map) || ($map['layer'] ?? null) !== $layer || ! is_string($map['imageSha256'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/', $map['imageSha256']) || $map['imageSha256'] !== strtolower($manifest['files'][$path] ?? '')
                || ($map['width'] ?? null) !== $size[0] || ($map['height'] ?? null) !== $size[1]
                || ! in_array($map['basis'] ?? null, ['illustration', 'photograph'], true)) {
                self::fail('Each region map must match the exact image hash, dimensions, layer and image basis.');
            }
            $sources = $map['sources'] ?? null;
            if (! is_array($sources) || (count($sources) < 1 && ! empty($map['parts'])) || count($sources) > 8) {
                self::fail('Each mapped image requires one to eight anatomy references.');
            }
            foreach ($sources as $source) {
                $url = $source['url'] ?? null;
                if (! is_array($source) || ! is_string($source['title'] ?? null) || trim($source['title']) === '' || mb_strlen($source['title']) > 200
                    || ! is_string($url) || strlen($url) > 2000 || ! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https'
                    || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null) {
                    self::fail('Anatomy references require a title and a public HTTPS URL without credentials.');
                }
                if (array_key_exists('appliesTo', $source)) {
                    if (! is_array($source['appliesTo']) || ! array_is_list($source['appliesTo']) || $source['appliesTo'] === [] || count($source['appliesTo']) > 150) {
                        self::fail('A structure-specific reference requires a list of structure IDs and exact labels.');
                    }
                    $seen = [];
                    foreach ($source['appliesTo'] as $target) {
                        $id = is_array($target) ? ($target['id'] ?? null) : null;
                        $label = is_array($target) ? ($target['label'] ?? null) : null;
                        if (! is_string($id) || isset($seen[$id])) {
                            self::fail('Reference bindings require distinct string structure IDs.');
                        }
                        $seen[$id] = true;
                        $part = $parts->get($id);
                        if (! $part || $part['layer'] !== $layer || $label !== $part['name']) {
                            self::fail('Each structure-specific reference must match an existing structure label on this image layer.');
                        }
                    }
                }
            }
            if (! is_array($map['parts'] ?? null) || ! is_array($map['unmapped'] ?? [])) {
                self::fail('Provide mapped parts and explicit reasons for unidentifiable structures.');
            }
            foreach ($map['parts'] as $id => $shape) {
                $part = $parts->get($id);
                if (! $part || $part['layer'] !== $layer || ! is_array($shape) || ($shape['label'] ?? null) !== $part['name']
                    || ! is_array($shape['polygons'] ?? null) || count($shape['polygons']) < 1 || count($shape['polygons']) > 20) {
                    self::fail('Every outline must identify an existing structure on the matching image layer.');
                }
                if (! collect($sources)->contains(fn ($source) => ! array_key_exists('appliesTo', $source)
                    || collect($source['appliesTo'])->contains(fn ($target) => $target['id'] === (string) $id && $target['label'] === $part['name']))) {
                    self::fail('Every outlined structure requires at least one applicable anatomy reference.');
                }
                foreach ($shape['polygons'] as $polygon) {
                    $vertices += is_array($polygon) ? count($polygon) : 0;
                    if ($vertices > 6000 || ! self::validPolygon($polygon)) {
                        self::fail('Use closed, non-crossing polygons with 3–200 distinct numeric points within 0–100 percent of the image.');
                    }
                }
                $mapped++;
            }
            foreach ($map['unmapped'] ?? [] as $id => $reason) {
                if (! $parts->has($id) || $parts[$id]['layer'] !== $layer || isset($map['parts'][$id]) || ! is_string($reason) || mb_strlen(trim($reason)) < 10 || mb_strlen($reason) > 1000) {
                    self::fail('Unmapped structures require a distinct structure ID and a meaningful reason.');
                }
            }
        }
        if (($manifest['catalogVisibility'] ?? null) === 'listed') {
            foreach ($parts as $part) {
                $map = $maps[$part['layer']] ?? [];
                if (! isset($map['parts'][$part['id']]) && ! isset($map['unmapped'][$part['id']])) {
                    self::fail('A newly listed species package must map each structure or explain why it cannot be outlined.');
                }
            }
            if ($mapped === 0) {
                self::fail('A newly listed species package needs at least one image-matched outline.');
            }
        }
    }

    public static function validPolygon(mixed $points): bool
    {
        if (! is_array($points) || ! array_is_list($points) || count($points) < 3 || count($points) > 200) {
            return false;
        }
        foreach ($points as $p) {
            if (! is_array($p) || ! array_is_list($p) || count($p) !== 2) {
                return false;
            }
            foreach ($p as $number) {
                if ((! is_int($number) && ! is_float($number)) || ! is_finite((float) $number) || $number < 0 || $number > 100) {
                    return false;
                }
            }
        }
        $n = count($points);
        $area = 0;
        for ($i = 0; $i < $n; $i++) {
            $a = $points[$i];
            $b = $points[($i + 1) % $n];
            if (hypot($a[0] - $b[0], $a[1] - $b[1]) < 1e-6) {
                return false;
            }
            $area += $a[0] * $b[1] - $b[0] * $a[1];
            for ($j = $i + 1; $j < $n; $j++) {
                if ($j === ($i + 1) % $n || ($j + 1) % $n === $i) {
                    continue;
                }
                if (self::intersects($a, $b, $points[$j], $points[($j + 1) % $n])) {
                    return false;
                }
            }
        }

        return abs($area) / 2 >= .001;
    }

    private static function intersects(array $a, array $b, array $c, array $d): bool
    {
        $cross = fn ($a, $b, $c) => ($b[0] - $a[0]) * ($c[1] - $a[1]) - ($b[1] - $a[1]) * ($c[0] - $a[0]);
        $between = fn ($p, $a, $b) => $p[0] >= min($a[0], $b[0]) - 1e-8 && $p[0] <= max($a[0], $b[0]) + 1e-8 && $p[1] >= min($a[1], $b[1]) - 1e-8 && $p[1] <= max($a[1], $b[1]) + 1e-8;
        $x = $cross($a, $b, $c);
        $y = $cross($a, $b, $d);
        $z = $cross($c, $d, $a);
        $w = $cross($c, $d, $b);

        return ($x * $y < -1e-12 && $z * $w < -1e-12)
            || (abs($x) < 1e-8 && $between($c, $a, $b)) || (abs($y) < 1e-8 && $between($d, $a, $b))
            || (abs($z) < 1e-8 && $between($a, $c, $d)) || (abs($w) < 1e-8 && $between($b, $c, $d));
    }

    private static function fail(string $message): never
    {
        throw ValidationException::withMessages(['anatomy' => $message]);
    }
}

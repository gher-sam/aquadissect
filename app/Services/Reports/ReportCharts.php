<?php

namespace App\Services\Reports;

class ReportCharts
{
    public const COLORS = ['0F766E', '155EEF', 'B7791F', '64748B', '7C3AED'];

    public function definitions(array $r): array
    {
        $charts = [];
        foreach (array_chunk($r['charts']['completion'], 12) as $index => $group) {
            $charts[] = ['key' => 'completion-'.$index, 'title' => 'Current assigned activity completion'.($index ? ' continued' : ''), 'type' => 'bar',
                'labels' => array_column($group, 'label'), 'series' => [
                    ['name' => 'Completed', 'values' => array_column($group, 'completed')],
                    ['name' => 'In progress', 'values' => array_column($group, 'inProgress')],
                    ['name' => 'Prerequisite pending', 'values' => array_column($group, 'blocked')],
                    ['name' => 'Not recorded', 'values' => array_column($group, 'notRecorded')],
                    ['name' => 'Exception', 'values' => array_column($group, 'exception')]],
                'note' => 'Current active assignments. Exceptions are shown separately from completed work.'];
        }
        $trend = $r['charts']['trend']['points'];
        if ($trend) {
            $charts[] = ['key' => 'trend', 'title' => 'Recorded learning activity', 'type' => 'line',
                'labels' => array_column($trend, 'date'), 'series' => [['name' => 'Records', 'values' => array_column($trend, 'records')],
                    ['name' => 'Unique learners', 'values' => array_column($trend, 'learners')]], 'note' => 'Saved records by '.$r['charts']['trend']['interval'].'. A learner can create multiple records.'];
        }
        if ($r['summary']['scoreSamples']) {
            $charts[] = ['key' => 'distribution', 'title' => 'Latest formative score distribution', 'type' => 'bar',
                'labels' => array_column($r['charts']['distribution'], 'label'), 'series' => [['name' => 'Latest results', 'values' => array_column($r['charts']['distribution'], 'count')]],
                'note' => 'One latest result per learner, section and lesson version in the selected period.'];
        }
        foreach (array_chunk(array_values(array_filter($r['assessments'], fn ($a) => $a['matchedPairs'] > 0)), 8) as $index => $group) {
            $charts[] = ['key' => 'assessments-'.$index, 'title' => 'Recorded paired assessment scores', 'type' => 'bar',
                'labels' => array_map(fn ($a) => $a['title'].' · '.$a['instrumentVersion'].' (n='.$a['matchedPairs'].')', $group),
                'series' => [['name' => 'Pretest mean %', 'values' => array_column($group, 'preMean')], ['name' => 'Posttest mean %', 'values' => array_column($group, 'postMean')]],
                'note' => 'Matched, non-voided pairs within each instrument. These differences do not establish learning effectiveness.'];
        }
        if ($r['forecast']['status'] === 'available') {
            $f = $r['forecast'];
            $labels = array_values(array_unique([...array_column($f['history'], 'date'), ...array_column($f['projection'], 'date')]));
            $actual = collect($f['history'])->pluck('completed', 'date');
            $projected = collect($f['projection'])->keyBy('date');
            $charts[] = ['key' => 'forecast', 'title' => 'Class completion forecast', 'type' => 'line', 'labels' => $labels,
                'series' => [
                    ['name' => 'Recorded completions', 'values' => array_map(fn ($d) => $actual[$d] ?? null, $labels)],
                    ['name' => 'Expected at recent pace', 'values' => array_map(fn ($d) => $projected[$d]['expected'] ?? null, $labels), 'dashed' => true],
                    ['name' => 'Slower recent weekly pace', 'values' => array_map(fn ($d) => $projected[$d]['low'] ?? null, $labels), 'dashed' => true],
                    ['name' => 'Faster recent weekly pace', 'values' => array_map(fn ($d) => $projected[$d]['high'] ?? null, $labels), 'dashed' => true]],
                'note' => $f['assumption']];
        }

        foreach ($charts as &$chart) {
            $palette = str_starts_with($chart['key'], 'completion-') ? self::COLORS
                : ($chart['key'] === 'forecast' ? ['155EEF', 'B7791F', 'B68632', 'B68632'] : ['155EEF', '0F766E']);
            foreach ($chart['series'] as $index => &$series) {
                $series['color'] = $palette[$index % count($palette)];
            }
            unset($series);
        }
        unset($chart);

        return $charts;
    }

    /** Server-rendered chart pixels come from the same authorized series as the dashboard. */
    public function png(array $chart, string $path): void
    {
        $width = 1500;
        $height = 720;
        $im = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($im, 255, 255, 255);
        $ink = imagecolorallocate($im, 39, 55, 79);
        $muted = imagecolorallocate($im, 94, 110, 132);
        $grid = imagecolorallocate($im, 224, 231, 241);
        imagefill($im, 0, 0, $white);
        imageantialias($im, true);
        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf');
        $text = static function (string $s, int $x, int $y, int $size = 16, $color = null) use ($im, $font, $ink) {
            imagettftext($im, $size, 0, $x, $y, $color ?? $ink, $font, $s);
        };
        $text($chart['title'], 32, 43, 28);
        $left = 78;
        $top = 110;
        $right = 1450;
        $bottom = 520;
        $values = array_merge(...array_column($chart['series'], 'values'));
        $max = max(1, ...array_map(fn ($v) => (float) ($v ?? 0), $values));
        $max = $max <= 10 ? ceil($max) : ceil($max / 5) * 5;
        for ($i = 0; $i <= 5; $i++) {
            $y = (int) round($bottom - ($bottom - $top) * $i / 5);
            imageline($im, $left, $y, $right, $y, $grid);
            $text((string) round($max * $i / 5, 1), 8, $y + 6, 22, $muted);
        }
        $n = count($chart['labels']);
        $seriesCount = count($chart['series']);
        $step = ($right - $left) / max(1, $n);
        $colors = array_map(fn ($hex) => imagecolorallocate($im, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))), array_column($chart['series'], 'color'));
        foreach ($chart['series'] as $j => $series) {
            $previous = null;
            $color = $colors[$j % count($colors)];
            imagesetthickness($im, 3);
            foreach ($series['values'] as $i => $value) {
                if ($value === null) {
                    $previous = null;

                    continue;
                }
                $x = (int) round($left + ($i + .5) * $step);
                $y = (int) round($bottom - ($bottom - $top) * $value / $max);
                if ($chart['type'] === 'bar') {
                    $bar = max(2, (int) ($step * .8 / $seriesCount));
                    $bx = (int) ($left + $i * $step + $step * .1 + $j * $bar);
                    imagefilledrectangle($im, $bx, $y, $bx + $bar - 2, $bottom, $color);
                } else {
                    if ($previous) {
                        if ($series['dashed'] ?? false) {
                            imagesetstyle($im, [$color, $color, $color, $color, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT]);
                            imageline($im, $previous[0], $previous[1], $x, $y, IMG_COLOR_STYLED);
                        } else {
                            imageline($im, $previous[0], $previous[1], $x, $y, $color);
                        }
                    }
                    imagefilledellipse($im, $x, $y, 6, 6, $color);
                    $previous = [$x, $y];
                }
            }
        }
        $labelEvery = $chart['type'] === 'line' ? max(1, (int) ceil($n / 8)) : 1;
        foreach ($chart['labels'] as $i => $label) {
            if ($i % $labelEvery && $i !== $n - 1) {
                continue;
            }
            if ($chart['type'] === 'line' && $i !== $n - 1 && $n - 1 - $i < $labelEvery * .75) {
                continue;
            }
            $x = (int) round($left + ($i + .5) * $step - min(90, $step * .45));
            if ($chart['type'] === 'line') {
                $x = (int) min($right - 125, max($left - 10, $left + ($i + .5) * $step - 65));
            }
            $maxChars = $chart['type'] === 'line' ? 12 : max(9, (int) ($step / 16));
            $lines = explode("\n", wordwrap(mb_strimwidth($label, 0, $maxChars * 3, '…'), $maxChars, "\n", true));
            foreach (array_slice($lines, 0, 3) as $line => $content) {
                $text($content, $x, 552 + $line * 29, $chart['type'] === 'line' ? 18 : 22, $muted);
            }
        }
        foreach ($chart['series'] as $j => $series) {
            $x = 34 + ($j % 3) * 480;
            $y = 645 + intdiv($j, 3) * 30;
            imagefilledrectangle($im, $x, $y - 14, $x + 22, $y + 2, $colors[$j % count($colors)]);
            $text($series['name'], $x + 32, $y, 22);
        }
        imagepng($im, $path);
        imagedestroy($im);
    }
}

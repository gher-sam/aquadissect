<?php

namespace App\Services\Reports;

use Illuminate\Support\Carbon;

class CompletionForecast
{
    public function calculate(array $items, int $horizon, Carbon $now): array
    {
        $base = ['method' => 'Recorded completion pace v1', 'horizon' => $horizon,
            'assumption' => 'An estimate if recent completion pace continues for the current assigned cohort. The range reflects recent weekly pace, not a statistical confidence interval.'];
        $items = array_values(array_filter($items, fn ($i) => $i['status'] !== 'Exception'));
        $n = count($items);
        $completed = count(array_filter($items, fn ($i) => $i['status'] === 'Completed'));
        $base += ['cohort' => $n, 'completed' => $completed];
        if ($n && $completed === $n) {
            return $base + ['status' => 'complete', 'message' => 'This assigned cohort has completed the activity.', 'history' => [], 'projection' => []];
        }
        if (! $n) {
            return $this->unavailable($base, 'No active assigned learners match this activity.');
        }
        $available = min(array_column($items, 'availableAt'));
        $today = $now->copy()->timezone('Asia/Manila')->startOfDay();
        $availableDay = Carbon::parse($available)->timezone('Asia/Manila')->startOfDay();
        $days = max(0, (int) $availableDay->diffInDays($today, false));
        $window = min(28, intdiv($days, 7) * 7);
        if ($window < 14) {
            return $this->unavailable($base, 'At least two complete weeks of activity history are needed.');
        }
        $start = $today->copy()->subDays($window);
        $dates = array_values(array_filter(array_map(fn ($i) => $i['completedAt'] ? Carbon::parse($i['completedAt'])->timezone('Asia/Manila') : null, $items),
            fn ($d) => $d && $d->gte($start) && $d->lt($today) && $d->gte($availableDay)));
        if (count($dates) < 5 || count(array_unique(array_map(fn ($d) => $d->format('Y-m-d'), $dates))) < 2) {
            return $this->unavailable($base, 'At least five first completions across two days in the recent history are needed.');
        }
        $weekly = array_fill(0, intdiv($window, 7), 0);
        foreach ($dates as $date) {
            $weekly[min(count($weekly) - 1, intdiv((int) $start->diffInDays($date), 7))]++;
        }
        $rate = count($dates) / $window;
        $slow = min($weekly) / 7;
        $fast = max($weekly) / 7;
        $history = [];
        for ($day = $start->copy(); $day->lte($today); $day->addDay()) {
            $end = $day->isSameDay($today) ? $now : $day->copy()->endOfDay();
            $count = count(array_filter($items, fn ($i) => $i['completedAt'] && Carbon::parse($i['completedAt'])->lte($end)));
            $history[] = ['date' => $day->format('Y-m-d'), 'completed' => $count];
        }
        $projection = [];
        for ($day = 0; $day <= $horizon; $day++) {
            $projection[] = ['date' => $today->copy()->addDays($day)->format('Y-m-d'),
                'expected' => round(min($n, $completed + $rate * $day), 2),
                'low' => round(min($n, $completed + $slow * $day), 2),
                'high' => round(min($n, $completed + $fast * $day), 2)];
        }
        $remainingDays = (int) ceil(($n - $completed) / $rate);

        return $base + ['status' => 'available', 'message' => 'If recent pace continues', 'windowDays' => $window,
            'observedCompletions' => count($dates), 'dailyPace' => round($rate, 3),
            'expectedAtHorizon' => (int) round(min($n, $completed + $rate * $horizon)),
            'expectedCompletionDate' => $remainingDays <= $horizon ? $today->copy()->addDays($remainingDays)->format('Y-m-d') : null,
            'history' => $history, 'projection' => $projection];
    }

    private function unavailable(array $base, string $message): array
    {
        return $base + ['status' => 'insufficient', 'message' => $message, 'history' => [], 'projection' => []];
    }
}

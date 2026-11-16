<?php

namespace App\Services;

use App\Models\PracticeCompletion;
use App\Models\SpeciesPackage;
use App\Models\User;

class PracticeChallenges
{
    public const POINTS = 10;

    public function __construct(private readonly SpeciesPackageContent $content) {}

    /** Generate practice only from the selected package, never formal assessment items. */
    public function definitions(SpeciesPackage $package): array
    {
        $structures = collect($this->content->manifest($package)['contentData']['anatomy']['structures'])
            ->filter(fn (array $part) => filled($part['id'] ?? null) && filled($part['name'] ?? null)
                && filled($part['summary'] ?? null) && filled($part['system'] ?? null))
            ->values()->all();
        // Keep the source order within each layer; internal organs are the first practice targets.
        usort($structures, fn (array $a, array $b) => ($b['layer'] === 'internal') <=> ($a['layer'] === 'internal'));
        $challenges = [];

        foreach (['function', 'system'] as $kind) {
            $seen = [];
            $count = 0;
            foreach ($structures as $part) {
                $answer = (string) $part[$kind === 'function' ? 'name' : 'system'];
                $clue = (string) $part[$kind === 'function' ? 'summary' : 'name'];
                // Avoid repeated/ambiguous clues and repeated system targets in this small set.
                $unique = mb_strtolower($kind === 'function' ? $clue : $answer);
                if (isset($seen[$unique])) {
                    continue;
                }
                $id = 'v1:'.$kind.':'.$part['id'];
                $options = array_values(array_unique(array_column($structures, $kind === 'function' ? 'name' : 'system')));
                $options = array_values(array_filter($options, fn ($option) => $option !== $answer));
                usort($options, fn ($a, $b) => strcmp(hash('sha256', $id.$a), hash('sha256', $id.$b)));
                $options = [...array_slice($options, 0, 3), $answer];
                if (count($options) < 2) {
                    continue;
                }
                usort($options, fn ($a, $b) => strcmp(hash('sha256', $id.':choice:'.$a), hash('sha256', $id.':choice:'.$b)));
                $challenges[] = [
                    'id' => $id,
                    'kind' => $kind,
                    'title' => $kind === 'function' ? 'Function finder' : 'System sorter',
                    'prompt' => $kind === 'function' ? 'Which structure matches this function?' : 'Which system or role matches this structure in the lesson?',
                    'clue' => $clue,
                    'hint' => $kind === 'function' ? 'Lesson system or role: '.$part['system'] : $part['summary'],
                    'options' => $options,
                    'answer' => $answer,
                    'explanation' => $part['name'].': '.$part['summary'].' Lesson system or role: '.$part['system'].'.',
                    'points' => self::POINTS,
                ];
                $seen[$unique] = true;
                if (++$count === 3) {
                    break;
                }
            }
        }

        return $challenges;
    }

    public function summary(array $definitions, SpeciesPackage $package, User $user, ?int $classId): array
    {
        $keys = array_column($definitions, 'id');
        $rows = $user->role === User::ROLE_STUDENT && $classId !== null
            ? PracticeCompletion::query()->where('user_id', $user->id)->where('class_id', $classId)
                ->where('species_package_id', $package->id)->whereIn('challenge_key', $keys)->get()
            : collect();
        $completed = $rows->pluck('challenge_key')->all();
        $finished = fn ($group) => count($group) > 0 && count(array_diff($group, $completed)) === 0;
        $functions = array_column(array_filter($definitions, fn ($item) => $item['kind'] === 'function'), 'id');
        $systems = array_column(array_filter($definitions, fn ($item) => $item['kind'] === 'system'), 'id');

        return [
            'xp' => (int) $rows->sum('points'),
            'maxXp' => count($keys) * self::POINTS,
            'completedIds' => $completed,
            'completed' => count($completed),
            'total' => count($keys),
            'complete' => $finished($keys),
            'badges' => [
                ['id' => 'first-discovery', 'name' => 'First discovery', 'description' => 'Complete your first practice challenge.', 'earned' => count($completed) > 0],
                ['id' => 'function-finder', 'name' => 'Function finder', 'description' => 'Complete every function challenge in this set.', 'earned' => $finished($functions)],
                ['id' => 'system-sorter', 'name' => 'System sorter', 'description' => 'Complete every system challenge in this set.', 'earned' => $finished($systems)],
                ['id' => 'practice-complete', 'name' => 'Practice complete', 'description' => 'Complete every challenge in this lesson set.', 'earned' => $finished($keys)],
            ],
        ];
    }
}

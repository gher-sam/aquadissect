<?php

namespace App\Services;

use App\Models\LearningModuleVersion;
use Illuminate\Validation\ValidationException;

class MediaPromptBuilder
{
    /** @param array<string, string> $options */
    public function build(LearningModuleVersion $version, array $options): string
    {
        $tool = collect($version->manifest['tools'] ?? [])->firstWhere('key', $options['toolKey']);
        if (! $tool) {
            throw ValidationException::withMessages(['toolKey' => 'Choose a tool from this module version.']);
        }

        $medium = $options['mediaType'] === 'video'
            ? 'a seamless 6-10 second instructional loop'
            : 'a high-resolution layered 2.5D educational illustration';
        $motion = $options['mediaType'] === 'video'
            ? 'Use a stable camera and one slow, physically plausible action. End on the starting composition for a clean loop.'
            : 'Separate foreground, tool, hand-position, safety-highlight, and background depth planes for subtle parallax.';

        return implode("\n\n", [
            'Create '.$medium.' for AquaDissect, an MB 304L virtual pre-laboratory learning system.',
            "Subject: {$tool['name']}. Purpose: {$tool['purpose']} Handling focus: {$tool['handling']}",
            "Composition: {$options['view']} view; {$options['action']} demonstration; {$options['ppe']} presentation. Use a clean laboratory tray context, accurate proportions, restrained navy, royal-blue, and aqua accents, flat color fields without gradients, soft studio lighting, and a neutral uncluttered background.",
            "Safety: {$tool['safety']} Show only instructor-supervised educational handling. Do not show unsafe grip, loose sharps, blood, gore, injury, brand marks, institutional seals, personal names, or identifying text.",
            $motion,
            'Accessibility and export: keep the subject readable at mobile size, preserve clear contrast, leave caption-safe space, include no baked-in labels, and provide a transparent or easily isolated background when the medium supports it.',
            'Status: draft media for faculty review. Do not present the result as an approved institutional procedure.',
        ]);
    }
}

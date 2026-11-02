<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FishProfileSchema
{
    public static function validate(array $manifest, ?string $directory = null): void
    {
        Validator::make($manifest, [
            'speciesProfile' => ['sometimes', 'array:description,bodyShape,mouthPosition,tailShape,finDescription,anatomicalDistinctions,habitat,sizeNotes,sources,localNames'],
            'speciesProfile.description' => 'nullable|string|max:5000',
            'speciesProfile.bodyShape' => 'nullable|string|max:2000',
            'speciesProfile.mouthPosition' => 'nullable|string|max:2000',
            'speciesProfile.tailShape' => 'nullable|string|max:2000',
            'speciesProfile.finDescription' => 'nullable|string|max:5000',
            'speciesProfile.anatomicalDistinctions' => 'nullable|string|max:5000',
            'speciesProfile.habitat' => 'nullable|string|max:3000',
            'speciesProfile.sizeNotes' => 'nullable|string|max:3000',
            'speciesProfile.sources' => 'nullable|string|max:5000',
            'speciesProfile.localNames' => 'sometimes|array|max:30',
            'speciesProfile.localNames.*' => 'array:name,language,locality,source',
            'speciesProfile.localNames.*.name' => 'required|string|max:150',
            'speciesProfile.localNames.*.language' => 'required|string|max:150',
            'speciesProfile.localNames.*.locality' => 'required|string|max:200',
            'speciesProfile.localNames.*.source' => 'required|string|max:500',
            'calibrations' => 'sometimes|array:natural,external,internal',
            'calibrations.*' => 'array:a,b,lengthMm,width,height,imageSha256,source,basis,scope',
            'calibrations.*.a' => 'required|array:x,y',
            'calibrations.*.b' => 'required|array:x,y',
            'calibrations.*.a.x' => 'required|numeric|between:0,1',
            'calibrations.*.a.y' => 'required|numeric|between:0,1',
            'calibrations.*.b.x' => 'required|numeric|between:0,1',
            'calibrations.*.b.y' => 'required|numeric|between:0,1',
            'calibrations.*.lengthMm' => 'required|numeric|gt:0|max:100000',
            'calibrations.*.width' => 'required|integer|min:1|max:50000',
            'calibrations.*.height' => 'required|integer|min:1|max:50000',
            'calibrations.*.imageSha256' => 'required|string|regex:/^[a-f0-9]{64}$/',
            'calibrations.*.source' => 'required|string|max:1000',
            'calibrations.*.basis' => 'required|in:photograph,illustrative_estimate',
            'calibrations.*.scope' => 'required|in:body,parts',
        ])->validate();

        foreach ($manifest['calibrations'] ?? [] as $key => $c) {
            $distance = hypot(($c['a']['x'] - $c['b']['x']) * $c['width'], ($c['a']['y'] - $c['b']['y']) * $c['height']);
            $file = $manifest['assets'][$key] ?? '';
            $matches = $file && ($manifest['files'][$file] ?? null) === $c['imageSha256'];
            if ($directory) {
                $image = @getimagesize($directory.DIRECTORY_SEPARATOR.$file);
                $matches = $matches && $image && $image[0] === $c['width'] && $image[1] === $c['height'];
            }
            if ($distance < 1 || ! $matches) {
                throw ValidationException::withMessages(['calibrations' => 'Calibration must match the image checksum, dimensions and a nonzero reference segment.']);
            }
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\SpeciesPackage;
use App\Services\FishProfileSchema;
use App\Services\SpeciesCatalog;
use App\Services\SpeciesPackageContent;
use App\Services\SpeciesPackageImporter;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class FishRevisionController extends Controller
{
    public function store(Request $request, SpeciesPackage $package, SpeciesPackageContent $content, SpeciesPackageImporter $importer, SpeciesCatalog $catalog)
    {
        $content->authorize($request->user(), $package);
        $data = $request->validate([
            'version' => ['required', 'string', 'max:40', 'regex:/^\\d+\\.\\d+\\.\\d+(?:-[a-zA-Z0-9.-]+)?$/'],
            'profile' => 'required|json|max:30000',
            'calibrations' => 'nullable|json|max:10000',
            'anatomy' => 'required_with:external,internal|nullable|json|max:100000',
            'video' => 'prohibited',
            'model' => 'prohibited',
            'natural' => 'nullable|file|mimes:png,jpg,jpeg,webp|max:20480',
            'external' => 'nullable|file|mimes:png,jpg,jpeg,webp|max:20480',
            'internal' => 'nullable|file|mimes:png,jpg,jpeg,webp|max:20480',
        ]);
        $manifest = $package->manifest;
        if (! is_array($manifest['files'] ?? null) || ! isset($manifest['content']['anatomy'])) {
            throw ValidationException::withMessages(['package' => 'This legacy record has no complete source archive. Import a complete new lesson package instead.']);
        }
        $manifest['packageVersion'] = $data['version'];
        // Imported revisions are data-only and cannot select executable adapters.
        if (isset($manifest['viewer']['optional3d']['adapter'])) {
            unset($manifest['viewer']['optional3d']);
        }
        $manifest['speciesProfile'] = json_decode($data['profile'], true, flags: JSON_THROW_ON_ERROR);
        $manifest['calibrations'] = json_decode($data['calibrations'] ?? '{}', true, flags: JSON_THROW_ON_ERROR);
        $manifest['draftNotice'] = 'Revised fish information, imagery and measurement references require independent faculty review before student publication.';
        $manifest['revisionOf'] = ['packageId' => $package->id, 'version' => $package->version];
        $temp = tempnam(storage_path('app/private'), 'revision-');
        $zip = new ZipArchive;
        if ($zip->open($temp, ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Cannot prepare the revision.');
        }
        $closed = false;
        try {
            foreach ($manifest['files'] as $file => $hash) {
                $key = array_search($file, $manifest['assets'], true);
                $upload = in_array($key, ['natural', 'external', 'internal'], true) ? $request->file($key) : null;
                if ($upload) {
                    // Keep the immutable old asset intact; the new archive receives the replacement.
                    $info = getimagesize($upload->getRealPath());
                    if (! $info || $info[0] * $info[1] > 20000000) {
                        throw ValidationException::withMessages([$key => 'Use an image of at most 20 megapixels.']);
                    }
                    if (! function_exists('imagecreatefromstring')) {
                        abort(503, 'Image re-encoding is unavailable.');
                    }
                    $image = imagecreatefromstring(file_get_contents($upload->getRealPath()));
                    imagepalettetotruecolor($image);
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                    ob_start();
                    imagewebp($image, null, 95);
                    $bytes = ob_get_clean();
                    imagedestroy($image);
                    $newFile = 'assets/'.$key.'-revision.webp';
                    $hash = hash('sha256', $bytes);
                    if (isset($manifest['calibrations'][$key])) {
                        throw ValidationException::withMessages(['calibrations' => 'Save replacement imagery without calibration, then calibrate its new immutable version.']);
                    }
                    unset($manifest['files'][$file]);
                    $manifest['assets'][$key] = $newFile;
                    $manifest['files'][$newFile] = $hash;
                    $manifest['assetProvenance'][$key] = ['source' => 'Staff-supplied replacement; see fish profile sources', 'reviewStatus' => 'awaiting_faculty_review', 'personalMetadataRemoved' => true];
                    $zip->addFromString($newFile, $bytes);
                } elseif ($file === $manifest['content']['anatomy'] && isset($data['anatomy'])) {
                    $json = json_decode($data['anatomy'], true, flags: JSON_THROW_ON_ERROR);
                    if (! is_array($json)) {
                        throw ValidationException::withMessages(['anatomy' => 'Supply a complete anatomy JSON object.']);
                    }
                    $bytes = json_encode($json, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                    $manifest['files'][$file] = hash('sha256', $bytes);
                    $zip->addFromString($file, $bytes);
                } else {
                    $zip->addFile($content->absolutePath($package, $file), $file);
                }
            }
            FishProfileSchema::validate($manifest);
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            $zip->close();
            $closed = true;
            $revision = $importer->import(new UploadedFile($temp, 'revision.aqpack', 'application/zip', null, true), $request->user());

            return response()->json(['package' => $catalog->packagePayload($revision, $request->user())], 201);
        } finally {
            if (! $closed) {
                $zip->close();
            }
            if (is_file($temp)) {
                unlink($temp);
            }
        }
    }
}

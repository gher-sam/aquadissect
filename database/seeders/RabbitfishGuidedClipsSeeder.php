<?php

namespace Database\Seeders;

use App\Models\SpeciesPackage;
use Illuminate\Support\Facades\DB;

class RabbitfishGuidedClipsSeeder extends SpeciesCatalogSeeder
{
    public const VERSION = '1.3.0';

    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (['1.2.0', self::VERSION] as $version) {
                $package = $this->installBundledPackage('rabbitfish', $version, SpeciesPackage::STATUS_REVIEW, null, false);
                if ($package->status === SpeciesPackage::STATUS_REVIEW) {
                    $package->events()->firstOrCreate(
                        ['event' => 'prepared_for_faculty_review'],
                        ['user_id' => null, 'metadata' => ['source' => 'bundled', 'reviewRequired' => true]],
                    );
                }
            }
        });
    }
}

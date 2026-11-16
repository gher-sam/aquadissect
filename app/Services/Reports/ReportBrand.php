<?php

namespace App\Services\Reports;

class ReportBrand
{
    public const INSTITUTION = 'Southern Leyte State University';

    public const CAMPUS = 'Bontoc Campus, San Ramon, Bontoc, Southern Leyte';

    public const EMAIL = 'cd_bt@southernleytestateu.edu.ph';

    public const WEBSITE = 'www.southernleytestateu.edu.ph';

    public const VALUES = 'Excellence | Service | Leadership and Good Governance | Innovation | Social Responsibility | Integrity | Professionalism | Spirituality';

    public static function path(string $name): string
    {
        return resource_path('reports/branding/'.$name.'.png');
    }

    public static function dataUri(string $name): string
    {
        return 'data:image/png;base64,'.base64_encode(file_get_contents(self::path($name)));
    }

    public static function period(array $report): string
    {
        return $report['filters']['from'].' to '.$report['filters']['to'].' · Philippine time';
    }

    public static function scope(array $report): string
    {
        $f = $report['filters'];
        $class = collect($report['options']['classes'])->firstWhere('id', (int) ($f['classId'] ?? 0));
        $activity = collect($report['options']['activities'])->firstWhere('key', $f['activityKey'] ?? '');

        return implode(' · ', array_filter([$class['name'] ?? 'All authorized sections', $f['academicYear'] ?? null,
            isset($f['semester']) ? 'Semester '.$f['semester'] : null, ucfirst($f['offeringStatus']).' sections', $activity['name'] ?? ucfirst($f['activity']),
            isset($f['learnerId']) ? 'Selected learner' : null]));
    }
}

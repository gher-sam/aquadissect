<?php

namespace App\Services;

use App\Models\Course;
use App\Models\LearningClass;

class CourseProvisioner
{
    public function ensure(): Course
    {
        return app(DelayedCloneWrite::class)->transaction(function (): Course {
            $course = Course::query()->firstOrCreate(
                ['code' => 'MB-304L'],
                ['name' => 'Marine Zoology II Vertebrates Laboratory'],
            );

            LearningClass::query()->whereNull('course_id')->orderBy('id')->chunkById(100, function ($classes) use ($course): void {
                foreach ($classes as $class) {
                    $class->update([
                        'course_id' => $course->id,
                        'academic_year' => $class->academic_year ?: 'Legacy',
                        'semester' => $class->semester ?: 'Unassigned',
                        'section_code' => $class->section_code ?: $class->code,
                    ]);
                }
            });

            return $course;
        });
    }
}

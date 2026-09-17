<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLessonAttemptRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'packageId' => ['required', 'integer', 'exists:species_packages,id'],
            'packageVersion' => ['required', 'string', 'max:40'],
            'classId' => ['nullable', 'integer', 'exists:classes,id'],
            'clientAttemptUuid' => ['required', 'uuid'],
            'source' => ['required', Rule::in(['online', 'offline'])],
            'completedAt' => ['required', 'date', 'before_or_equal:now'],
            'answers' => ['required', 'array', 'min:1'],
            'answers.*' => ['required', 'string', 'max:500'],
        ];
    }
}

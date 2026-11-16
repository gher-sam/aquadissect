<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LearningReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() ?? false;
    }

    public function rules(): array
    {
        return [
            'classId' => ['nullable', 'integer'],
            'academicYear' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'string', 'max:30'],
            'offeringStatus' => ['nullable', Rule::in(['active', 'archived', 'all'])],
            'activity' => ['nullable', Rule::in(['all', 'lessons', 'safety', 'assessments', 'practice'])],
            'activityKey' => ['nullable', 'string', 'max:80', 'regex:/^(lesson|safety|assessment):[1-9][0-9]*$/'],
            'learnerId' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->format('Y-m-d'), ...($this->filled('from') ? ['after_or_equal:from'] : [])],
            'scoreThreshold' => ['nullable', 'numeric', 'between:0,100'],
            'horizon' => ['nullable', Rule::in([7, 14, 30])],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:160'],
            'sort' => ['nullable', Rule::in(['date_desc', 'date_asc', 'learner'])],
            'layout' => ['nullable', Rule::in(['summary', 'detailed'])],
            'format' => ['nullable', Rule::in(['pdf', 'xlsx', 'csv', 'docx', 'xml'])],
        ];
    }
}

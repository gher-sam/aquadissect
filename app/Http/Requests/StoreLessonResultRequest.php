<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreLessonResultRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'score' => ['required', 'integer', 'min:0'],
            'total' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->integer('score') > $this->integer('total')) {
                    $validator->errors()->add('score', 'The score cannot exceed the total.');
                }
            },
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'classCode' => strtoupper(trim((string) $this->input('classCode'))),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'firstName' => ['required', 'string', 'max:80'],
            'lastName' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'identityNumber' => ['required', 'string', 'max:80', 'unique:users,identity_number'],
            'role' => ['required', Rule::in(User::REGISTRABLE_ROLES)],
            'classCode' => [
                'nullable',
                'string',
                'max:80',
                'required_if:role,'.User::ROLE_STUDENT,
                Rule::exists('classes', 'code')->where('status', 'active'),
            ],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'agreement' => ['accepted'],
        ];
    }

    /** @return array<string, mixed> */
    public function userAttributes(): array
    {
        $data = $this->validated();
        $isStudent = $data['role'] === User::ROLE_STUDENT;

        return [
            'first_name' => $data['firstName'],
            'last_name' => $data['lastName'],
            'email' => strtolower($data['email']),
            'identity_number' => $data['identityNumber'],
            'role' => $data['role'],
            'status' => $isStudent ? User::STATUS_ACTIVE : User::STATUS_PENDING,
            'class_code' => $isStudent ? $data['classCode'] : null,
            'password' => $data['password'],
        ];
    }
}

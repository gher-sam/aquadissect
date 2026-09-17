<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'firstName' => ['required', 'string', 'max:80'],
            'lastName' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'identityNumber' => ['required', 'string', 'max:80', 'unique:users,identity_number'],
            'role' => ['required', Rule::in(User::REGISTRABLE_ROLES)],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers(), 'max:255'],
            'agreement' => ['accepted'],
        ];
    }

    /** @return array<string, mixed> */
    public function userAttributes(): array
    {
        $data = $this->validated();

        return [
            'first_name' => $data['firstName'],
            'last_name' => $data['lastName'],
            'email' => strtolower($data['email']),
            'identity_number' => $data['identityNumber'],
            'role' => $data['role'],
            'status' => User::STATUS_PENDING,
            'class_code' => null,
            'password' => $data['password'],
        ];
    }
}

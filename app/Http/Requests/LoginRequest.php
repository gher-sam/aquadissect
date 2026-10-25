<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $identifier = $this->input('identifier', $this->input('email', ''));
        $this->merge(['identifier' => is_string($identifier) ? trim($identifier) : $identifier]);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function credentials(): array
    {
        $identifier = $this->validated('identifier');
        $password = $this->validated('password');
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return ['email' => strtolower($identifier), 'password' => $password];
        }

        return ['identity_number' => fn (Builder $query) => $query->whereRaw('UPPER(identity_number) = ?', [strtoupper($identifier)]),
            'role' => User::ROLE_STUDENT, 'password' => $password];
    }
}

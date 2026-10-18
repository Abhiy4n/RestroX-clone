<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cleans up and validates the login form before LoginController runs.
 */
class LoginRequest extends FormRequest
{
    /**
     * Anyone can try to log in, so there is nothing to check here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Trim and lowercase the email so it matches how it was saved at sign-up.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    /**
     * The rules each field must pass.
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The validated email address.
     */
    public function email(): string
    {
        return (string) $this->validated('email');
    }

    /**
     * The validated password.
     */
    public function password(): string
    {
        return (string) $this->validated('password');
    }
}

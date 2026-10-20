<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\NormalizePhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Cleans up and validates the sign-up form before RegisterController runs.
 *
 * If validation fails, Laravel replies with a 422 error and the controller never runs.
 */
class RegisterRequest extends FormRequest
{
    /**
     * Anyone can sign up, so there is nothing to check here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Tidy the input before validating it.
     *
     * Emails are trimmed and lowercased so "  User@Example.com " and "user@example.com"
     * count as the same account. Phone numbers lose their spaces and dashes.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'phone_country_iso2' => $this->filled('phone_e164')
                ? mb_strtoupper((string) $this->input('phone_country_iso2'))
                : null,
            'phone_e164' => (new NormalizePhone)->handle($this->input('phone_e164')),
        ]);
    }

    /**
     * The rules each field must pass.
     *
     * Phone numbers must be in E.164 format, e.g. +9779812345678. Passwords need at least
     * 8 characters with upper and lower case letters and a number.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'phone_country_iso2' => ['nullable', 'required_with:phone_e164', 'string', 'size:2', 'alpha'],
            'phone_e164' => ['nullable', 'required_with:phone_country_iso2', 'string', 'max:16', 'regex:/^\+[1-9]\d{7,14}$/', 'unique:users,phone_e164'],
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers(), 'confirmed'],
        ];
    }
}

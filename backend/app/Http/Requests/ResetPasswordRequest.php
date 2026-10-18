<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validates the "Set a new password" form before NewPasswordController runs.
 */
class ResetPasswordRequest extends FormRequest
{
    /**
     * Anyone with a valid reset token may use this form; the token itself is checked later.
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
     * The rules each field must pass. The new password follows the same rules as sign-up.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'lowercase'],
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers(), 'confirmed'],
        ];
    }
}

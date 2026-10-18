<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the "Forgot password?" form before PasswordResetLinkController runs.
 */
class SendPasswordResetLinkRequest extends FormRequest
{
    /**
     * Anyone can ask for a reset link, so there is nothing to check here.
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
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'lowercase'],
        ];
    }
}

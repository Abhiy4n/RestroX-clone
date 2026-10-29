<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class StorePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->attributes->has('authenticated_merchant');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'merchant_reference' => ['required', 'string', 'max:100', 'regex:/\S/'],
            'amount_minor' => ['required', 'integer', 'min:1', 'max:999999999999999'],
            'currency' => ['required', Rule::in(['NPR'])],
            'description' => ['nullable', 'string', 'max:255'],
            'return_url' => ['required', 'url:http,https', 'max:2048'],
            'cancel_url' => ['nullable', 'url:http,https', 'max:2048'],
            'idempotency_key' => ['required', 'string', 'max:255', 'regex:/\S/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'merchant_reference' => trim((string) $this->input('merchant_reference')),
            'currency' => strtoupper(trim((string) $this->input('currency'))),
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
            'return_url' => trim((string) $this->input('return_url')),
            'cancel_url' => $this->filled('cancel_url') ? trim((string) $this->input('cancel_url')) : null,
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }
}

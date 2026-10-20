<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddMoneyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('issue-test-money') === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'wallet_id' => ['required', 'integer'],
            'amount_minor' => ['required', 'integer', 'min:1', 'max:999999999999999'],
            'idempotency_key' => ['required', 'string', 'max:255', 'regex:/\S/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Merchant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class StoreMerchantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Merchant::class) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'website_url' => ['nullable', 'url:http,https', 'max:255'],
            'integration_type' => [
                'required',
                Rule::in([
                    Merchant::INTEGRATION_WEBSITE,
                    Merchant::INTEGRATION_MOBILE_APP,
                    Merchant::INTEGRATION_SERVER_API,
                ]),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'website_url' => $this->filled('website_url')
                ? trim((string) $this->input('website_url'))
                : null,
        ]);
    }
}

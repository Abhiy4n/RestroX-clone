<?php

namespace App\Http\Requests;

use App\Models\Merchant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class UpdateMerchantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('merchant'));

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'website_url' => ['sometimes', 'nullable', 'url:http,https', 'max:255'],
            'integration_type' => [
                'sometimes',
                'required',
                Rule::in([
                    Merchant::INTEGRATION_WEBSITE,
                    Merchant::INTEGRATION_MOBILE_APP,
                    Merchant::INTEGRATION_SERVER_API,
                ]),
            ],
            'status' => [
                'sometimes',
                'required',
                Rule::in([Merchant::STATUS_ACTIVE, Merchant::STATUS_DISABLED]),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('name')) {
            $normalized['name'] = trim((string) $this->input('name'));
        }

        if ($this->has('website_url')) {
            $normalized['website_url'] = $this->filled('website_url')
                ? trim((string) $this->input('website_url'))
                : null;
        }

        $this->merge($normalized);
    }
}

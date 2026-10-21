<?php

namespace App\Http\Requests;

use App\Models\Merchant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

#[FailOnUnknownFields]
class StoreMerchantApiKeyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $routeMerchant = $this->route('merchant');

        if ($routeMerchant instanceof Merchant) {
            Gate::authorize('update', $routeMerchant);

            return true;
        }

        if (! $this->user()?->is_active || ! $this->filled('merchant_id')) {
            return $this->user()?->is_active === true;
        }

        $merchant = Merchant::query()->where('code', $this->string('merchant_id')->toString())->first();

        if ($merchant !== null) {
            Gate::authorize('update', $merchant);
        }

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
            'merchant_id' => [
                Rule::requiredIf(! ($this->route('merchant') instanceof Merchant)),
                Rule::prohibitedIf($this->route('merchant') instanceof Merchant),
                'string',
                Rule::exists('merchants', 'code'),
            ],
            'name' => ['required', 'string', 'max:100'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->merchant()->status !== Merchant::STATUS_ACTIVE) {
                    $validator->errors()->add('merchant_id', 'API keys can only be generated for an active merchant.');
                }
            },
        ];
    }

    public function merchant(): Merchant
    {
        $routeMerchant = $this->route('merchant');

        if ($routeMerchant instanceof Merchant) {
            return $routeMerchant;
        }

        return Merchant::query()
            ->where('code', $this->string('merchant_id')->toString())
            ->firstOrFail();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
        ]);
    }
}

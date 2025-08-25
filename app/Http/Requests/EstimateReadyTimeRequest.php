<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EstimateReadyTimeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'location_products' => ['required', 'array', 'min:1'],
            'location_products.*.location_product_id' => ['required', 'integer', 'exists:location_products,id'],
            'location_products.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'location_products.required' => 'At least one product must be specified for estimation.',
            'location_products.*.location_product_id.exists' => 'The selected product is not available.',
            'location_products.*.quantity.min' => 'Quantity must be at least 1.',
        ];
    }
}

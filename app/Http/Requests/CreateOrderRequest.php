<?php

namespace App\Http\Requests;

use App\Enums\OrderSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CreateOrderRequest extends FormRequest
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
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'source' => ['required', new Enum(OrderSource::class)],
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
            'location_products.required' => 'At least one product must be ordered.',
            'location_products.*.location_product_id.exists' => 'The selected product is not available.',
            'location_products.*.quantity.min' => 'Quantity must be at least 1.',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Product;

use Illuminate\Validation\Rule;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, \Illuminate\Validation\Rules\Unique|string>> */
    public function rules(): array
    {
        return [


            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'name'),
            ],
            'description' => ['nullable', 'string'],
            'price_in_cents' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'images' => ['sometimes', 'array'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
        ];
    }
}



<?php

declare(strict_types=1);

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'           => ['sometimes', 'string', 'max:255'],
            'description'    => ['sometimes', 'nullable', 'string'],
            'price_in_cents' => ['sometimes', 'integer', 'min:0'],
            'stock'          => ['sometimes', 'integer', 'min:0'],
            'is_active'      => ['sometimes', 'boolean'],
            'images'         => ['sometimes', 'array'],
            'images.*'       => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge(['is_active' => filter_var($this->is_active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $this->is_active]);
        }

        if ($this->has('price_in_cents')) {
            $this->merge(['price_in_cents' => (int) $this->price_in_cents]);
        }

        if ($this->has('stock')) {
            $this->merge(['stock' => (int) $this->stock]);
        }
    }
}



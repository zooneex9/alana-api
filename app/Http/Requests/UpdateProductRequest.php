<?php

namespace App\Http\Requests;

use App\Support\DressTaxonomy;
use App\Support\ProductColors;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('admin');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('quantity') && is_string($this->input('quantity'))) {
            $this->merge(['quantity' => (int) $this->input('quantity')]);
        }
        if ($this->has('rental_price_daily') && is_string($this->input('rental_price_daily'))) {
            $this->merge(['rental_price_daily' => (float) $this->input('rental_price_daily')]);
        }
        if ($this->has('rental_price_weekend') && $this->input('rental_price_weekend') !== '') {
            $this->merge(['rental_price_weekend' => (float) $this->input('rental_price_weekend')]);
        }
        if ($this->has('deposit') && $this->input('deposit') !== '') {
            $this->merge(['deposit' => (float) $this->input('deposit')]);
        }
        if ($this->has('rental_duration_days') && is_string($this->input('rental_duration_days'))) {
            $this->merge(['rental_duration_days' => (int) $this->input('rental_duration_days')]);
        }
        if ($this->has('price') && is_string($this->input('price'))) {
            $this->merge(['price' => (float) $this->input('price')]);
        }
        if ($this->has('rental_price_daily') && ! $this->has('price')) {
            $this->merge(['price' => (float) $this->input('rental_price_daily')]);
        }
        foreach (['is_vintage', 'is_new_arrival', 'is_dr_fave'] as $flag) {
            if ($this->has($flag)) {
                $this->merge([$flag => filter_var($this->input($flag), FILTER_VALIDATE_BOOLEAN)]);
            }
        }
        if ($this->has('occasions') && is_string($this->input('occasions'))) {
            $decoded = json_decode($this->input('occasions'), true);
            if (is_array($decoded)) {
                $this->merge(['occasions' => $decoded]);
            }
        }
        if ($this->has('categories') && is_string($this->input('categories'))) {
            $decoded = json_decode($this->input('categories'), true);
            if (is_array($decoded)) {
                $this->merge(['categories' => $decoded]);
            }
        }
        if ($this->filled('category') && ! $this->has('categories')) {
            $this->merge(['categories' => [(string) $this->input('category')]]);
        }
        if ($this->has('colors') && is_string($this->input('colors'))) {
            $decoded = json_decode($this->input('colors'), true);
            if (is_array($decoded)) {
                $this->merge(['colors' => $decoded]);
            }
        }
        if ($this->has('colors')) {
            $this->merge(['colors' => ProductColors::normalizeList($this->input('colors'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'string', 'max:2000'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'rental_price_daily' => ['sometimes', 'numeric', 'min:0'],
            'rental_price_weekend' => ['nullable', 'numeric', 'min:0'],
            'deposit' => ['nullable', 'numeric', 'min:0'],
            'rental_duration_days' => ['sometimes', 'integer', 'min:1', 'max:90'],
            'quantity' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', 'in:available,reserved,rented'],
            'category' => ['sometimes', 'string', 'max:120'],
            'categories' => ['nullable', 'array', 'max:12'],
            'categories.*' => ['string', 'max:120'],
            'dress_length' => ['nullable', 'string', 'in:'.implode(',', DressTaxonomy::LENGTHS)],
            'occasions' => DressTaxonomy::occasionRules(),
            'occasions.*' => DressTaxonomy::occasionItemRules(),
            'is_vintage' => ['nullable', 'boolean'],
            'is_new_arrival' => ['nullable', 'boolean'],
            'is_dr_fave' => ['nullable', 'boolean'],
            'size' => ['nullable', 'string', 'max:32'],
            'color' => ['nullable', 'string', 'max:64'],
            'colors' => ['nullable', 'array', 'max:'.ProductColors::MAX],
            'colors.*' => ['regex:/^#[0-9A-Fa-f]{6}$/'],
            'date_added' => ['sometimes', 'date'],
            'images' => ['nullable', 'array', 'max:20'],
            'images.*' => ['file', 'image', 'max:5120'],
            'image_urls' => ['nullable', 'string', 'max:8192'],
            'remove_image_paths' => ['nullable', 'string', 'max:8192'],
            'remove_image_urls' => ['nullable', 'string', 'max:8192'],
        ];
    }
}

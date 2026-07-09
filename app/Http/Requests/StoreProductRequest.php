<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
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
        if (! $this->filled('price') && $this->filled('rental_price_daily')) {
            $this->merge(['price' => (float) $this->input('rental_price_daily')]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:2000'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'rental_price_daily' => ['required', 'numeric', 'min:0'],
            'rental_price_weekend' => ['nullable', 'numeric', 'min:0'],
            'deposit' => ['nullable', 'numeric', 'min:0'],
            'rental_duration_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'quantity' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:available,reserved,rented'],
            'category' => ['required', 'string', 'max:120'],
            'size' => ['nullable', 'string', 'max:32'],
            'color' => ['nullable', 'string', 'max:64'],
            'date_added' => ['nullable', 'date'],
            'images' => ['nullable', 'array', 'max:20'],
            'images.*' => ['file', 'image', 'max:5120'],
            'image_urls' => ['nullable', 'string', 'max:8192'],
        ];
    }
}

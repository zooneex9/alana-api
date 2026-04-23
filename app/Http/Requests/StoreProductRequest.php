<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:available,separated,sold'],
            'payment_type' => ['required', 'in:full,installment'],
            'down_payment' => ['nullable', 'numeric', 'min:0'],
            'installments' => ['nullable', 'integer', 'min:1', 'max:24'],
            'category' => ['required', 'string', 'max:120'],
            'date_added' => ['nullable', 'date'],
            'image' => ['nullable', 'image', 'max:5120'],
            'image_url' => ['nullable', 'url', 'max:2048'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'string', 'max:2000'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', 'in:available,separated,sold'],
            'payment_type' => ['sometimes', 'in:full,installment'],
            'down_payment' => ['nullable', 'numeric', 'min:0'],
            'installments' => ['nullable', 'integer', 'min:1', 'max:24'],
            'category' => ['sometimes', 'string', 'max:120'],
            'date_added' => ['sometimes', 'date'],
            'image' => ['sometimes', 'image', 'max:5120'],
            'image_url' => ['sometimes', 'url', 'max:2048'],
        ];
    }
}

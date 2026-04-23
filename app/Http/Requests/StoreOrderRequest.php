<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'buyer_name' => ['required', 'string', 'max:120'],
            'buyer_email' => ['required', 'email'],
            'buyer_phone' => ['required', 'string', 'max:40'],
            'buyer_address' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string', 'max:50'],
            'order_date' => ['required', 'date'],
            'status' => ['required', 'in:pending,completed,cancelled'],
        ];
    }
}

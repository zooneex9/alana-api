<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateCheckoutSessionRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'mode' => ['required', 'in:buy,separate'],
            /** Índice en product.payment_plans (0-based); obligatorio con varios planes a meses */
            'payment_plan_index' => ['nullable', 'integer', 'min:0'],
            'buyer_name' => ['required', 'string', 'max:120'],
            'buyer_email' => ['required', 'email'],
            'buyer_phone' => ['required', 'string', 'max:40'],
            'buyer_address' => ['required', 'string', 'max:500'],
            'requires_invoice' => ['sometimes', 'boolean'],
        ];
    }
}

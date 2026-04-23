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
            'buyer_name' => ['required', 'string', 'max:120'],
            'buyer_email' => ['required', 'email'],
            'buyer_phone' => ['required', 'string', 'max:40'],
            'buyer_address' => ['required', 'string', 'max:500'],
        ];
    }
}

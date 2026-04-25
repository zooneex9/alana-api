<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('admin');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('payment_plans') && is_string($this->input('payment_plans'))) {
            $decoded = json_decode($this->input('payment_plans'), true);
            $this->merge(['payment_plans' => is_array($decoded) ? $decoded : []]);
        }
        if ($this->has('quantity') && is_string($this->input('quantity'))) {
            $this->merge(['quantity' => (int) $this->input('quantity')]);
        }
        if ($this->has('shipping_to_agree')) {
            $v = $this->input('shipping_to_agree');
            if (is_string($v)) {
                $this->merge(['shipping_to_agree' => filter_var($v, FILTER_VALIDATE_BOOLEAN)]);
            }
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if (! $this->has('payment_plans')) {
                return;
            }
            $plans = $this->input('payment_plans', []);
            if (! is_array($plans)) {
                return;
            }
            foreach ($plans as $i => $plan) {
                if (! is_array($plan)) {
                    $v->errors()->add('payment_plans', 'Cada plan debe ser un objeto válido.');

                    return;
                }
                if (($plan['type'] ?? '') === 'installment') {
                    if (! array_key_exists('down_payment', $plan) || ! array_key_exists('periods', $plan) || ! array_key_exists('frequency', $plan)) {
                        $v->errors()->add("payment_plans.{$i}", 'Plan a plazos: indica enganche, frecuencia y número de periodos.');

                        return;
                    }
                }
            }
        });
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
            'quantity' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', 'in:available,separated,sold'],
            'payment_plans' => ['sometimes', 'array'],
            'payment_plans.*.type' => ['required', 'in:full,installment'],
            'payment_plans.*.down_payment' => ['nullable', 'numeric', 'min:0'],
            'payment_plans.*.installments' => ['nullable', 'integer', 'min:1', 'max:24'],
            'payment_plans.*.periods' => ['nullable', 'integer', 'min:1', 'max:52'],
            'payment_plans.*.frequency' => ['nullable', 'in:weekly,monthly'],
            'category' => ['sometimes', 'string', 'max:120'],
            'item_condition' => ['sometimes', 'in:new,used_like_new,used_good'],
            'shipping_to_agree' => ['sometimes', 'boolean'],
            'date_added' => ['sometimes', 'date'],
            'images' => ['nullable', 'array', 'max:20'],
            'images.*' => ['file', 'image', 'max:5120'],
            'image_urls' => ['nullable', 'string', 'max:8192'],
            'remove_image_paths' => ['nullable', 'string', 'max:8192'],
            'remove_image_urls' => ['nullable', 'string', 'max:8192'],
        ];
    }
}

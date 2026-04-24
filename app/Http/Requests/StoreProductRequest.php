<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProductRequest extends FormRequest
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
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $plans = $this->input('payment_plans', []);
            if (! is_array($plans)) {
                return;
            }
            foreach ($plans as $i => $plan) {
                if (! is_array($plan)) {
                    $v->errors()->add('payment_plans', 'Cada plan debe ser un objeto válido.');

                    return;
                }
                $type = $plan['type'] ?? '';
                if ($type === 'installment') {
                    if (! array_key_exists('down_payment', $plan) || ! array_key_exists('installments', $plan)) {
                        $v->errors()->add("payment_plans.{$i}", 'Plan a meses: indica enganche y número de pagos.');

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
            'name' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'quantity' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:available,separated,sold'],
            'payment_plans' => ['present', 'array'],
            'payment_plans.*.type' => ['required', 'in:full,installment'],
            'payment_plans.*.down_payment' => ['nullable', 'numeric', 'min:0'],
            'payment_plans.*.installments' => ['nullable', 'integer', 'min:1', 'max:24'],
            'category' => ['required', 'string', 'max:120'],
            'item_condition' => ['required', 'in:new,used_like_new,used_good'],
            'date_added' => ['nullable', 'date'],
            'images' => ['nullable', 'array', 'max:20'],
            'images.*' => ['file', 'image', 'max:5120'],
            'image_urls' => ['nullable', 'string', 'max:8192'],
        ];
    }
}

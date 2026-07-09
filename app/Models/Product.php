<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'quantity',
        'status',
        'payment_plans',
        'category',
        'size',
        'color',
        'rental_price_daily',
        'rental_price_weekend',
        'deposit',
        'rental_duration_days',
        'item_condition',
        'shipping_to_agree',
        'date_added',
        'images',
    ];

    protected $casts = [
        'shipping_to_agree' => 'boolean',
        'price' => 'float',
        'rental_price_daily' => 'float',
        'rental_price_weekend' => 'float',
        'deposit' => 'float',
        'rental_duration_days' => 'integer',
        'quantity' => 'integer',
        'date_added' => 'date',
        'payment_plans' => 'array',
        'images' => 'array',
    ];

    protected $appends = [
        'image_url',
    ];

    /**
     * @return array<int, array{path: ?string, url: ?string}>
     */
    public function imagesList(): array
    {
        $i = $this->images;

        return is_array($i) ? $i : [];
    }

    public function getImageUrlAttribute(): ?string
    {
        $list = $this->imagesList();
        if ($list === []) {
            return null;
        }
        $first = $list[0];

        return is_array($first) ? ($first['url'] ?? null) : null;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function rentalBlocks(): HasMany
    {
        return $this->hasMany(RentalBlock::class);
    }

    /**
     * @return array<int, array{type: string, down_payment?: float, installments?: int}>
     */
    public function paymentPlansList(): array
    {
        $p = $this->payment_plans;

        if (! is_array($p)) {
            return [];
        }

        return array_map(function ($plan) {
            if (! is_array($plan) || ($plan['type'] ?? '') !== 'installment') {
                return ['type' => 'full'];
            }
            $periods = (int) ($plan['periods'] ?? $plan['installments'] ?? 1);

            return [
                'type' => 'installment',
                'down_payment' => (float) ($plan['down_payment'] ?? 0),
                'periods' => max(1, $periods),
                'frequency' => ($plan['frequency'] ?? 'monthly') === 'weekly' ? 'weekly' : 'monthly',
                // compatibilidad legacy
                'installments' => max(1, $periods),
            ];
        }, $p);
    }

    /**
     * En JSON, fechas sin hora (evita "2026-04-24T00:00:00.000000Z" en el cliente).
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }
}

<?php

namespace App\Models;

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
        'item_condition',
        'date_added',
        'images',
    ];

    protected $casts = [
        'price' => 'float',
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

    /**
     * @return array<int, array{type: string, down_payment?: float, installments?: int}>
     */
    public function paymentPlansList(): array
    {
        $p = $this->payment_plans;

        return is_array($p) ? $p : [];
    }
}

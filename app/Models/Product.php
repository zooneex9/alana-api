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
        'status',
        'payment_type',
        'down_payment',
        'installments',
        'category',
        'date_added',
        'image_path',
        'image_url',
    ];

    protected $casts = [
        'price' => 'float',
        'down_payment' => 'float',
        'installments' => 'integer',
        'date_added' => 'date',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'buyer_name',
        'buyer_email',
        'buyer_phone',
        'buyer_address',
        'amount',
        'payment_method',
        'order_date',
        'status',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'stripe_refund_id',
        'checkout_url',
        'paid_at',
        'refund_amount',
        'refund_reason',
        'refunded_at',
        'meta',
    ];

    protected $casts = [
        'amount' => 'float',
        'order_date' => 'date',
        'paid_at' => 'datetime',
        'refund_amount' => 'float',
        'refunded_at' => 'datetime',
        'meta' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

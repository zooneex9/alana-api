<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;

class DashboardController extends Controller
{
    public function summary()
    {
        $available = Product::query()->where('status', 'available')->count();
        $reserved = Product::query()->where('status', 'reserved')->count();
        $rented = Product::query()->where('status', 'rented')->count();

        return response()->json([
            'stats' => [
                'available' => $available,
                'reserved' => $reserved,
                'rented' => $rented,
            ],
        ]);
    }
}

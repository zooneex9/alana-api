<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;

class DashboardController extends Controller
{
    public function summary()
    {
        $available = Product::query()->where('status', 'available')->count();
        $separated = Product::query()->where('status', 'separated')->count();
        $sold = Product::query()->where('status', 'sold')->count();
        $totalRevenue = Order::query()
            ->where('status', 'completed')
            ->sum('amount');

        $recentOrders = Order::query()
            ->with('product')
            ->latest()
            ->limit(5)
            ->get();

        return response()->json([
            'stats' => [
                'available' => $available,
                'separated' => $separated,
                'sold' => $sold,
                'total_revenue' => (float) $totalRevenue,
            ],
            'recent_orders' => $recentOrders,
        ]);
    }
}

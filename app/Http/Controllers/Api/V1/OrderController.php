<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCheckoutSessionRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class OrderController extends Controller
{
    private const VAT_RATE = 0.16;

    private function surchargePctByPeriods(int $periods): float
    {
        if ($periods < 3) {
            return 0.0;
        }

        return 10.0 + (float) (intdiv($periods - 3, 3) * 3);
    }

    /**
     * @return array{
     *  base: float,
     *  down_payment: float,
     *  remaining: float,
     *  periods: int,
     *  frequency: string,
     *  surcharge_pct: float,
     *  surcharge_amount: float,
     *  subtotal_before_vat: float,
     *  vat_amount: float,
     *  total: float,
     *  charge_now: float
     * }
     */
    private function checkoutAmounts(Product $product, string $mode, array $plans, int $idx, bool $requiresInvoice): array
    {
        $base = (float) $product->price;
        $downPayment = 0.0;
        $remaining = $base;
        $periods = 1;
        $frequency = 'monthly';
        $surchargePct = 0.0;
        $surchargeAmount = 0.0;
        $subtotalBeforeVat = $base;
        $chargeNow = $base;

        if ($mode === 'separate') {
            $plan = $plans[$idx];
            $downPayment = max(0.0, (float) ($plan['down_payment'] ?? 0));
            $periods = max(1, (int) ($plan['periods'] ?? $plan['installments'] ?? 1));
            $frequency = (($plan['frequency'] ?? 'monthly') === 'weekly') ? 'weekly' : 'monthly';
            $remaining = max(0.0, $base - $downPayment);
            $surchargePct = $this->surchargePctByPeriods($periods);
            $surchargeAmount = $remaining * ($surchargePct / 100);
            $subtotalBeforeVat = $downPayment + $remaining + $surchargeAmount;
            $chargeNow = $downPayment;
        }

        $vatAmount = $requiresInvoice ? ($subtotalBeforeVat * self::VAT_RATE) : 0.0;
        $total = $subtotalBeforeVat + $vatAmount;
        if ($mode === 'buy' && $requiresInvoice) {
            $chargeNow = $total;
        }

        return [
            'base' => round($base, 2),
            'down_payment' => round($downPayment, 2),
            'remaining' => round($remaining, 2),
            'periods' => $periods,
            'frequency' => $frequency,
            'surcharge_pct' => round($surchargePct, 2),
            'surcharge_amount' => round($surchargeAmount, 2),
            'subtotal_before_vat' => round($subtotalBeforeVat, 2),
            'vat_amount' => round($vatAmount, 2),
            'total' => round($total, 2),
            'charge_now' => round($chargeNow, 2),
        ];
    }

    public function index()
    {
        $orders = Order::query()
            ->with('product')
            ->latest()
            ->paginate(25);

        return response()->json($orders);
    }

    public function store(StoreOrderRequest $request)
    {
        $order = Order::query()->create($request->validated());
        $product = $order->product;
        if ($product) {
            $product->decrement('quantity', 1);
            $product->refresh();
            $product->update($product->quantity < 1
                ? ['status' => 'sold', 'quantity' => 0]
                : ['status' => 'available']);
        }

        return response()->json($order->load('product'), 201);
    }

    public function show(Order $order)
    {
        return response()->json($order->load('product'));
    }

    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'in:pending,completed,cancelled'],
            'buyer_name' => ['sometimes', 'string', 'max:120'],
            'buyer_email' => ['sometimes', 'email'],
            'buyer_phone' => ['sometimes', 'string', 'max:40'],
            'buyer_address' => ['sometimes', 'string', 'max:500'],
        ]);

        $order->update($validated);

        if (($validated['status'] ?? null) === 'completed') {
            $order->product()->update(['status' => 'sold']);
        }

        return response()->json($order->fresh()->load('product'));
    }

    public function createCheckoutSession(CreateCheckoutSessionRequest $request)
    {
        $validated = $request->validated();
        $product = Product::query()->findOrFail($validated['product_id']);

        if (in_array($product->status, ['sold', 'separated'], true)) {
            return response()->json([
                'message' => 'Este producto no está disponible para compra en línea.',
            ], 422);
        }

        if ((int) $product->quantity < 1) {
            return response()->json([
                'message' => 'Sin stock.',
            ], 422);
        }

        $mode = $validated['mode'];
        $plans = $product->paymentPlansList();
        $idx = (int) ($validated['payment_plan_index'] ?? 0);
        $requiresInvoice = (bool) ($validated['requires_invoice'] ?? false);

        if ($mode === 'separate') {
            if (! isset($plans[$idx]) || ($plans[$idx]['type'] ?? '') !== 'installment') {
                return response()->json([
                    'message' => 'Elige un plan a plazos válido (enganche + periodos) para apartar.',
                ], 422);
            }
        }

        $breakdown = $this->checkoutAmounts($product, $mode, $plans, $idx, $requiresInvoice);
        $chargeAmount = (float) $breakdown['charge_now'];

        if ($chargeAmount <= 0) {
            return response()->json([
                'message' => 'Monto a cobrar inválido.',
            ], 422);
        }

        $order = Order::query()->create([
            'product_id' => $product->id,
            'buyer_name' => $validated['buyer_name'],
            'buyer_email' => $validated['buyer_email'],
            'buyer_phone' => $validated['buyer_phone'],
            'buyer_address' => $validated['buyer_address'],
            'amount' => $chargeAmount,
            'payment_method' => 'stripe',
            'status' => 'pending',
            'order_date' => now()->toDateString(),
            'meta' => [
                'mode' => $mode,
                'payment_plan_index' => $idx,
                'requires_invoice' => $requiresInvoice,
                'pricing' => $breakdown,
            ],
        ]);

        if ($mode === 'separate' && $product->status === 'available') {
            $product->update(['status' => 'separated']);
        }

        if (app()->environment('testing')) {
            $fakeSessionId = 'cs_test_'.uniqid();
            $fakeUrl = rtrim(config('app.frontend_url'), '/')."/checkout/mock?session_id={$fakeSessionId}";

            $order->update([
                'stripe_checkout_session_id' => $fakeSessionId,
                'checkout_url' => $fakeUrl,
            ]);

            return response()->json([
                'checkout_url' => $fakeUrl,
                'order' => $order->fresh()->load('product'),
            ], 201);
        }

        try {
            $stripe = new StripeClient(config('services.stripe.secret'));

            /** @var Session $session */
            $session = $stripe->checkout->sessions->create([
                'mode' => 'payment',
                'customer_email' => $order->buyer_email,
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => 'mxn',
                        'unit_amount' => (int) round($chargeAmount * 100),
                        'product_data' => [
                            'name' => $product->name,
                            'description' => $product->description,
                        ],
                    ],
                ]],
                'metadata' => [
                    'order_id' => (string) $order->id,
                    'product_id' => (string) $product->id,
                    'mode' => $mode,
                    'payment_plan_index' => (string) $idx,
                    'requires_invoice' => $requiresInvoice ? '1' : '0',
                ],
                'success_url' => rtrim(config('app.frontend_url'), '/').'/checkout/success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => rtrim(config('app.frontend_url'), '/').'/checkout/cancel?order_id='.$order->id,
            ]);
        } catch (ApiErrorException $exception) {
            $order->update(['status' => 'cancelled']);

            return response()->json([
                'message' => 'Stripe checkout session creation failed.',
                'error' => $exception->getMessage(),
            ], 500);
        }

        $order->update([
            'stripe_checkout_session_id' => $session->id,
            'checkout_url' => $session->url,
        ]);

        return response()->json([
            'checkout_url' => $session->url,
            'order' => $order->fresh()->load('product'),
        ], 201);
    }

    public function refund(Request $request, Order $order)
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        if ($order->status !== 'completed') {
            return response()->json([
                'message' => 'Only completed orders can be refunded.',
            ], 422);
        }

        if (! $order->stripe_payment_intent_id) {
            return response()->json([
                'message' => 'Order has no Stripe payment intent.',
            ], 422);
        }

        if ($order->refunded_at) {
            return response()->json([
                'message' => 'Order is already refunded.',
            ], 422);
        }

        $reason = $validated['reason'] ?? null;
        $refundAmount = (float) $order->amount;

        if (app()->environment('testing')) {
            $refundId = 're_test_'.uniqid();
        } else {
            try {
                $stripe = new StripeClient(config('services.stripe.secret'));
                $stripeRefund = $stripe->refunds->create([
                    'payment_intent' => $order->stripe_payment_intent_id,
                    'metadata' => [
                        'order_id' => (string) $order->id,
                    ],
                    'reason' => $reason ?: null,
                ]);
                $refundId = $stripeRefund->id;
            } catch (ApiErrorException $exception) {
                return response()->json([
                    'message' => 'Stripe refund failed.',
                    'error' => $exception->getMessage(),
                ], 500);
            }
        }

        $order->update([
            'status' => 'cancelled',
            'stripe_refund_id' => $refundId,
            'refund_amount' => $refundAmount,
            'refund_reason' => $reason,
            'refunded_at' => now(),
        ]);

        $order->product()->update(['status' => 'available']);

        return response()->json([
            'message' => 'Refund created successfully.',
            'order' => $order->fresh()->load('product'),
        ]);
    }
}

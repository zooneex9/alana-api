<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    private function markProductSoldFromInitial(Order $order): void
    {
        $product = $order->product;
        if (! $product) {
            return;
        }

        $mode = $order->meta['mode'] ?? 'buy';
        if ($mode === 'separate') {
            return;
        }

        $product->decrement('quantity', 1);
        $product->refresh();
        $attributes = $product->quantity < 1
            ? ['status' => 'sold', 'quantity' => 0]
            : ['status' => 'available'];
        $product->update($attributes);
    }

    private function markInstallmentPaid(Order $order, object $session): void
    {
        $period = (string) ($session->metadata->period ?? '');
        if ($period === '') {
            return;
        }

        $meta = $order->meta ?? [];
        $installments = $meta['installment_sessions'] ?? [];
        if (! is_array($installments) || ! isset($installments[$period])) {
            return;
        }

        $installments[$period]['status'] = 'completed';
        $installments[$period]['payment_intent'] = $session->payment_intent ?? null;
        $installments[$period]['paid_at'] = Carbon::now()->toISOString();
        $meta['installment_sessions'] = $installments;
        $order->update(['meta' => $meta]);
    }

    private function markShippingPaid(Order $order, object $session): void
    {
        $id = (string) ($session->metadata->shipping_charge_id ?? '');
        if ($id === '') {
            return;
        }

        $meta = $order->meta ?? [];
        $charges = $meta['shipping_charges'] ?? [];
        if (! is_array($charges) || ! isset($charges[$id])) {
            return;
        }

        $charges[$id]['status'] = 'completed';
        $charges[$id]['payment_intent'] = $session->payment_intent ?? null;
        $charges[$id]['paid_at'] = Carbon::now()->toISOString();
        $meta['shipping_charges'] = $charges;
        $order->update(['meta' => $meta]);
    }

    public function __invoke(Request $request)
    {
        $signature = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        if (app()->environment('testing')) {
            $event = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);
        } elseif (! $signature || ! $secret) {
            return response()->json(['message' => 'Missing stripe webhook configuration.'], 400);
        } else {
            try {
                $event = Webhook::constructEvent($request->getContent(), $signature, $secret);
            } catch (\UnexpectedValueException|SignatureVerificationException $exception) {
                return response()->json(['message' => $exception->getMessage()], 400);
            }
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $orderId = $session->metadata->order_id ?? null;
            $checkoutKind = $session->metadata->checkout_kind ?? 'initial';
            $parentOrderId = $session->metadata->parent_order_id ?? $orderId;
            $isChildSession = in_array($checkoutKind, ['installment', 'shipping'], true);
            $lookupKey = $isChildSession ? $parentOrderId : $orderId;

            $order = Order::query()
                ->when(
                    $lookupKey,
                    fn ($query) => $query->whereKey($lookupKey),
                    fn ($query) => $query->where('stripe_checkout_session_id', $session->id)
                )
                ->first();

            if ($order) {
                if ($checkoutKind === 'installment') {
                    $this->markInstallmentPaid($order, $session);
                } elseif ($checkoutKind === 'shipping') {
                    $this->markShippingPaid($order, $session);
                } else {
                    $order->update([
                        'status' => 'completed',
                        'stripe_payment_intent_id' => $session->payment_intent ?? null,
                        'paid_at' => Carbon::now(),
                    ]);
                    $this->markProductSoldFromInitial($order);
                }
            }
        }

        if ($event->type === 'checkout.session.expired' || $event->type === 'checkout.session.async_payment_failed') {
            $session = $event->data->object;
            $checkoutKind = $session->metadata->checkout_kind ?? 'initial';
            $order = null;

            if (in_array($checkoutKind, ['installment', 'shipping'], true)) {
                $parentOrderId = $session->metadata->parent_order_id ?? null;
                if ($parentOrderId) {
                    $order = Order::query()->whereKey($parentOrderId)->first();
                }
            } else {
                $order = Order::query()->where('stripe_checkout_session_id', $session->id)->first();
            }

            if ($order) {
                if ($checkoutKind === 'installment') {
                    $period = (string) ($session->metadata->period ?? '');
                    $meta = $order->meta ?? [];
                    $installments = $meta['installment_sessions'] ?? [];
                    if (is_array($installments) && $period !== '' && isset($installments[$period])) {
                        $installments[$period]['status'] = 'cancelled';
                        $meta['installment_sessions'] = $installments;
                        $order->update(['meta' => $meta]);
                    }
                } elseif ($checkoutKind === 'shipping') {
                    $id = (string) ($session->metadata->shipping_charge_id ?? '');
                    $meta = $order->meta ?? [];
                    $charges = $meta['shipping_charges'] ?? [];
                    if (is_array($charges) && $id !== '' && isset($charges[$id])) {
                        $charges[$id]['status'] = 'cancelled';
                        $meta['shipping_charges'] = $charges;
                        $order->update(['meta' => $meta]);
                    }
                } else {
                    $order->update(['status' => 'cancelled']);

                    if ($order->product && $order->product->status === 'separated') {
                        $order->product()->update(['status' => 'available']);
                    }
                }
            }
        }

        return response()->json(['received' => true]);
    }
}

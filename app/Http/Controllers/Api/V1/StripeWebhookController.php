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

            $order = Order::query()
                ->when(
                    $orderId,
                    fn ($query) => $query->whereKey($orderId),
                    fn ($query) => $query->where('stripe_checkout_session_id', $session->id)
                )
                ->first();

            if ($order) {
                $order->update([
                    'status' => 'completed',
                    'stripe_payment_intent_id' => $session->payment_intent ?? null,
                    'paid_at' => Carbon::now(),
                ]);
                $product = $order->product;
                if ($product) {
                    $product->decrement('quantity', 1);
                    $product->refresh();
                    $attributes = $product->quantity < 1
                        ? ['status' => 'sold', 'quantity' => 0]
                        : ['status' => 'available'];
                    $product->update($attributes);
                }
            }
        }

        if ($event->type === 'checkout.session.expired' || $event->type === 'checkout.session.async_payment_failed') {
            $session = $event->data->object;
            $order = Order::query()->where('stripe_checkout_session_id', $session->id)->first();

            if ($order) {
                $order->update(['status' => 'cancelled']);

                if ($order->product && $order->product->status === 'separated') {
                    $order->product()->update(['status' => 'available']);
                }
            }
        }

        return response()->json(['received' => true]);
    }
}

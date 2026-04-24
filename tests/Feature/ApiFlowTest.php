<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin');
        Role::findOrCreate('buyer');
    }

    public function test_admin_can_login_and_fetch_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@bodega.test',
            'password' => bcrypt('password123'),
        ]);
        $user->assignRole('admin');

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@bodega.test',
            'password' => 'password123',
        ]);

        $loginResponse->assertOk()->assertJsonStructure([
            'token',
            'user' => ['id', 'email', 'roles'],
        ]);

        $token = $loginResponse->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'admin@bodega.test');
    }

    public function test_admin_can_create_product_and_update_status(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $token = $admin->createToken('test')->plainTextToken;

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/products', [
                'name' => 'MacBook Air M4',
                'description' => '16GB RAM, 512GB SSD',
                'price' => 29999,
                'quantity' => 2,
                'status' => 'available',
                'payment_plans' => [
                    [
                        'type' => 'installment',
                        'down_payment' => 9000,
                        'installments' => 4,
                    ],
                ],
                'category' => 'Electronics',
                'item_condition' => 'new',
                'date_added' => now()->toDateString(),
            ]);

        $create->assertCreated()->assertJsonPath('name', 'MacBook Air M4');
        $productId = $create->json('id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/products/{$productId}/status", ['status' => 'separated'])
            ->assertOk()
            ->assertJsonPath('status', 'separated');
    }

    public function test_checkout_session_and_webhook_marks_order_completed(): void
    {
        $product = Product::factory()->create([
            'status' => 'available',
            'price' => 5000,
            'quantity' => 1,
            'payment_plans' => [
                ['type' => 'full'],
            ],
        ]);

        $checkout = $this->postJson('/api/v1/orders/checkout-session', [
            'product_id' => $product->id,
            'mode' => 'buy',
            'buyer_name' => 'Jane Doe',
            'buyer_email' => 'jane@example.com',
            'buyer_phone' => '+525511112222',
            'buyer_address' => 'CDMX',
        ]);

        $checkout->assertCreated();
        $sessionId = $checkout->json('order.stripe_checkout_session_id');
        $orderId = $checkout->json('order.id');

        $payload = json_encode([
            'id' => 'evt_test_123',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'payment_intent' => 'pi_test_123',
                    'metadata' => [
                        'order_id' => (string) $orderId,
                        'product_id' => (string) $product->id,
                        'mode' => 'buy',
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", config('services.stripe.webhook_secret'));
        $header = "t={$timestamp},v1={$signature}";

        $this->withHeaders([
            'Stripe-Signature' => $header,
            'CONTENT_TYPE' => 'application/json',
        ])->call(
            'POST',
            '/api/v1/stripe/webhook',
            [],
            [],
            [],
            [],
            $payload
        )->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'completed',
            'stripe_payment_intent_id' => 'pi_test_123',
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'status' => 'sold',
            'quantity' => 0,
        ]);
    }

    public function test_admin_can_refund_completed_order(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = $admin->createToken('test')->plainTextToken;

        $product = Product::factory()->create([
            'status' => 'sold',
        ]);

        $order = Order::factory()->create([
            'product_id' => $product->id,
            'status' => 'completed',
            'payment_method' => 'stripe',
            'stripe_payment_intent_id' => 'pi_test_refund_1',
            'amount' => 2500,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/orders/{$order->id}/refund", [
                'reason' => 'requested_by_customer',
            ]);

        $response->assertOk()->assertJsonPath('order.status', 'cancelled');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
            'refund_reason' => 'requested_by_customer',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'status' => 'available',
        ]);
    }
}

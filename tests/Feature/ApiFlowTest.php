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
        Role::findOrCreate('customer');
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
                        'periods' => 4,
                        'frequency' => 'monthly',
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
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $checkout->assertCreated();
        $sessionId = $checkout->json('order.stripe_checkout_session_id');
        $orderId = $checkout->json('order.id');
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'user_id' => \App\Models\User::query()->where('email', 'jane@example.com')->value('id'),
        ]);

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

    public function test_separate_initial_webhook_does_not_decrement_stock(): void
    {
        $product = Product::factory()->create([
            'status' => 'available',
            'price' => 10000,
            'quantity' => 2,
            'payment_plans' => [
                ['type' => 'installment', 'down_payment' => 2000, 'periods' => 4, 'frequency' => 'weekly'],
            ],
        ]);

        $checkout = $this->postJson('/api/v1/orders/checkout-session', [
            'product_id' => $product->id,
            'mode' => 'separate',
            'payment_plan_index' => 0,
            'buyer_name' => 'Jane Doe',
            'buyer_email' => 'jane.separate@example.com',
            'buyer_phone' => '+525511112222',
            'buyer_address' => 'CDMX',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $checkout->assertCreated();
        $sessionId = $checkout->json('order.stripe_checkout_session_id');
        $orderId = $checkout->json('order.id');

        $payload = json_encode([
            'id' => 'evt_test_999',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'payment_intent' => 'pi_test_sep_1',
                    'metadata' => [
                        'order_id' => (string) $orderId,
                        'checkout_kind' => 'initial',
                        'mode' => 'separate',
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->postJson('/api/v1/stripe/webhook', json_decode($payload, true))->assertOk();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'quantity' => 2,
            'status' => 'separated',
        ]);
    }

    public function test_admin_can_create_installment_checkout_session_and_webhook_updates_meta(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = $admin->createToken('test')->plainTextToken;

        $order = Order::factory()->create([
            'status' => 'completed',
            'meta' => [
                'mode' => 'separate',
                'pricing' => [
                    'periods' => 3,
                    'per_period_amount' => 1000,
                ],
            ],
        ]);

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/orders/{$order->id}/installment-checkout-session", ['period' => 2]);
        $create->assertCreated()->assertJsonPath('period', 2);

        $order->refresh();
        $sessionId = $order->meta['installment_sessions']['2']['session_id'] ?? null;
        $this->assertNotNull($sessionId);

        $payload = [
            'id' => 'evt_inst_1',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'payment_intent' => 'pi_inst_2',
                    'metadata' => [
                        'checkout_kind' => 'installment',
                        'parent_order_id' => (string) $order->id,
                        'period' => '2',
                    ],
                ],
            ],
        ];

        $this->postJson('/api/v1/stripe/webhook', $payload)->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $order->refresh();
        $this->assertSame('completed', $order->meta['installment_sessions']['2']['status']);
    }

    public function test_admin_shipping_checkout_session_applies_vat_when_order_requires_invoice(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = $admin->createToken('test')->plainTextToken;

        $order = Order::factory()->create([
            'status' => 'completed',
            'meta' => [
                'mode' => 'buy',
                'requires_invoice' => true,
            ],
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/orders/{$order->id}/shipping-checkout-session", [
                'amount' => 100,
                'note' => 'DHL',
            ])
            ->assertCreated()
            ->assertJsonPath('amount_subtotal', 100)
            ->assertJsonPath('amount_charged', 116);
    }

    public function test_shipping_webhook_marks_charge_completed_without_stock_change(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = $admin->createToken('test')->plainTextToken;

        $product = Product::factory()->create([
            'status' => 'available',
            'quantity' => 3,
        ]);

        $order = Order::factory()->create([
            'product_id' => $product->id,
            'status' => 'completed',
            'meta' => [
                'mode' => 'buy',
                'requires_invoice' => false,
            ],
        ]);

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/orders/{$order->id}/shipping-checkout-session", ['amount' => 80]);
        $create->assertCreated();
        $chargeId = $create->json('shipping_charge_id');
        $this->assertNotEmpty($chargeId);

        $order->refresh();
        $sessionId = $order->meta['shipping_charges'][$chargeId]['session_id'] ?? null;
        $this->assertNotNull($sessionId);

        $payload = [
            'id' => 'evt_ship_1',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'payment_intent' => 'pi_ship_1',
                    'metadata' => [
                        'checkout_kind' => 'shipping',
                        'parent_order_id' => (string) $order->id,
                        'shipping_charge_id' => $chargeId,
                    ],
                ],
            ],
        ];

        $this->postJson('/api/v1/stripe/webhook', $payload)->assertOk();

        $order->refresh();
        $this->assertSame('completed', $order->meta['shipping_charges'][$chargeId]['status']);
        $product->refresh();
        $this->assertSame(3, $product->quantity);
    }

    public function test_admin_can_assign_customer_and_customer_can_see_own_orders(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $adminToken = $admin->createToken('test')->plainTextToken;

        $order = Order::factory()->create([
            'buyer_name' => 'Cliente Demo',
            'buyer_email' => 'cliente@example.com',
        ]);

        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->postJson("/api/v1/orders/{$order->id}/assign-customer", [
                'name' => 'Cliente Demo',
                'email' => 'cliente@example.com',
                'password' => 'password123',
            ])
            ->assertOk();

        $login = $this->postJson('/api/v1/auth/customer/login', [
            'email' => 'cliente@example.com',
            'password' => 'password123',
        ]);
        $login->assertOk();

        $customerToken = $login->json('token');
        $this->withHeader('Authorization', "Bearer {$customerToken}")
            ->getJson('/api/v1/customer/orders')
            ->assertOk()
            ->assertJsonStructure(['data']);
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

    public function test_checkout_session_applies_invoice_vat_to_buy_mode(): void
    {
        $product = Product::factory()->create([
            'status' => 'available',
            'price' => 1000,
            'quantity' => 1,
            'payment_plans' => [
                ['type' => 'full'],
            ],
        ]);

        $checkout = $this->postJson('/api/v1/orders/checkout-session', [
            'product_id' => $product->id,
            'mode' => 'buy',
            'requires_invoice' => true,
            'buyer_name' => 'Jane Doe',
            'buyer_email' => 'jane.invoice@example.com',
            'buyer_phone' => '+525511112222',
            'buyer_address' => 'CDMX',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $checkout->assertCreated()
            ->assertJsonPath('order.amount', 1160)
            ->assertJsonPath('order.meta.requires_invoice', true)
            ->assertJsonPath('order.meta.pricing.vat_amount', 160)
            ->assertJsonPath('order.meta.pricing.total', 1160);
    }
}

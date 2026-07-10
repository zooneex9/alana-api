<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\RentalBlock;
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
    }

    public function test_admin_can_login_and_fetch_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@alana.test',
            'password' => bcrypt('password123'),
        ]);
        $user->assignRole('admin');

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@alana.test',
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
            ->assertJsonPath('user.email', 'admin@alana.test');
    }

    public function test_admin_can_create_dress_and_update_status(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = $admin->createToken('test')->plainTextToken;

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/products', [
                'name' => 'Vestido Rosa',
                'description' => 'Vestido largo para gala',
                'rental_price_daily' => 1200,
                'quantity' => 1,
                'status' => 'available',
                'category' => 'Gala',
                'size' => 'M',
                'color' => 'Rosa',
                'date_added' => now()->toDateString(),
            ]);

        $create->assertCreated()->assertJsonPath('name', 'Vestido Rosa');
        $productId = $create->json('id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/products/{$productId}/status", ['status' => 'reserved'])
            ->assertOk()
            ->assertJsonPath('status', 'reserved');
    }

    public function test_public_can_list_products_and_check_availability(): void
    {
        $product = Product::factory()->create();

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.0.id', $product->id);

        RentalBlock::query()->create([
            'product_id' => $product->id,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'status' => 'reserved',
        ]);

        $this->getJson("/api/v1/products/{$product->id}/availability")
            ->assertOk()
            ->assertJsonPath('product_id', $product->id)
            ->assertJsonCount(1, 'blocks');
    }

    public function test_admin_can_manage_rental_blocks(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = $admin->createToken('test')->plainTextToken;
        $product = Product::factory()->create();

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rental-blocks', [
                'product_id' => $product->id,
                'start_date' => now()->addDays(2)->toDateString(),
                'end_date' => now()->addDays(4)->toDateString(),
                'status' => 'reserved',
                'customer_name' => 'María',
            ]);

        $create->assertCreated()->assertJsonPath('customer_name', 'María');
        $blockId = $create->json('id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/rental-blocks?product_id='.$product->id)
            ->assertOk()
            ->assertJsonPath('data.0.id', $blockId);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/rental-blocks/{$blockId}")
            ->assertNoContent();
    }

    public function test_admin_can_manage_customers_and_product_rental_history(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = $admin->createToken('test')->plainTextToken;
        $product = Product::factory()->create();

        $createCustomer = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/customers', [
                'name' => 'Ana García',
                'phone' => '8181234567',
                'email' => 'ana@example.com',
            ]);

        $createCustomer->assertCreated()->assertJsonPath('name', 'Ana García');
        $customerId = $createCustomer->json('id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rental-blocks', [
                'product_id' => $product->id,
                'customer_id' => $customerId,
                'start_date' => '2026-07-01',
                'end_date' => '2026-07-03',
                'status' => 'reserved',
            ])
            ->assertCreated()
            ->assertJsonPath('customer.name', 'Ana García');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$product->id}/rental-history")
            ->assertOk()
            ->assertJsonPath('product_id', $product->id)
            ->assertJsonCount(1, 'rentals');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/customers/{$customerId}")
            ->assertOk()
            ->assertJsonPath('name', 'Ana García')
            ->assertJsonCount(1, 'rental_blocks');
    }

    public function test_rental_blocks_cannot_overlap(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = $admin->createToken('test')->plainTextToken;
        $product = Product::factory()->create();

        RentalBlock::query()->create([
            'product_id' => $product->id,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-15',
            'status' => 'blocked',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rental-blocks', [
                'product_id' => $product->id,
                'start_date' => '2026-06-12',
                'end_date' => '2026-06-18',
                'status' => 'reserved',
            ])
            ->assertStatus(422);
    }

    public function test_products_can_be_filtered_by_dress_taxonomy(): void
    {
        $match = Product::factory()->create([
            'dress_length' => 'largo',
            'occasions' => ['boda', 'night_out'],
            'is_vintage' => true,
            'is_new_arrival' => true,
            'is_dr_fave' => true,
        ]);

        Product::factory()->create([
            'dress_length' => 'corto',
            'occasions' => ['posada'],
            'is_vintage' => false,
            'is_new_arrival' => false,
            'is_dr_fave' => false,
        ]);

        $this->getJson('/api/v1/products?dress_length=largo')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);

        $this->getJson('/api/v1/products?occasions=boda,night_out')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);

        $this->getJson('/api/v1/products?vintage=1&new=1&faves=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_admin_can_save_dress_taxonomy_on_product(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = $admin->createToken('test')->plainTextToken;

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/products', [
                'name' => 'Vintage Boda',
                'description' => 'Vestido largo vintage para boda',
                'rental_price_daily' => 1500,
                'quantity' => 1,
                'status' => 'available',
                'category' => 'Gala',
                'dress_length' => 'largo',
                'occasions' => ['boda', 'boda_playa'],
                'is_vintage' => true,
                'is_new_arrival' => true,
                'is_dr_fave' => true,
                'date_added' => now()->toDateString(),
            ]);

        $create->assertCreated()
            ->assertJsonPath('dress_length', 'largo')
            ->assertJsonPath('is_vintage', true)
            ->assertJsonPath('is_dr_fave', true);
    }
}

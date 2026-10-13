<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_product_with_stock_rows_for_each_branch(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_super_admin' => true])->save();
        $category = Category::create(['name' => 'Minuman']);
        $firstBranch = Branch::create(['code' => 'CB-001', 'name' => 'Cabang Satu']);
        $secondBranch = Branch::create(['code' => 'CB-002', 'name' => 'Cabang Dua']);

        $this->actingAs($admin)->post(route('products.store'), [
            'category_id' => $category->id,
            'sku' => 'MIN-001',
            'barcode' => '899000000001',
            'name' => 'Air Mineral',
            'unit' => 'botol',
            'cost_price' => '2000.00',
            'sale_price' => '3500.00',
        ])->assertRedirect(route('products.index'));

        $product = Product::query()->where('sku', 'MIN-001')->firstOrFail();
        $this->assertDatabaseCount('branch_stocks', 2);
        $this->assertDatabaseHas('branch_stocks', [
            'branch_id' => $firstBranch->id,
            'product_id' => $product->id,
            'quantity' => '0.000',
        ]);
        $this->assertDatabaseHas('branch_stocks', [
            'branch_id' => $secondBranch->id,
            'product_id' => $product->id,
            'quantity' => '0.000',
        ]);

        $this->get(route('products.index'))->assertOk();
        $this->get(route('products.edit', $product))->assertOk();
        $this->get(route('categories.index'))->assertOk();
        $this->get(route('categories.create'))->assertOk();
    }

    public function test_regular_users_cannot_manage_products(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('products.index'))
            ->assertForbidden();
    }
}
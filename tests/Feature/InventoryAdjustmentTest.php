<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_adjust_stock_and_audit_the_change(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_super_admin' => true])->save();
        $branch = Branch::create(['code' => 'CB-001', 'name' => 'Cabang Pusat']);
        $category = Category::create(['name' => 'Minuman']);
        $product = Product::create([
            'category_id' => $category->id,
            'sku' => 'MIN-001',
            'name' => 'Air Mineral',
            'unit' => 'botol',
            'cost_price' => 2000,
            'sale_price' => 3500,
        ]);
        $stock = BranchStock::create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->actingAs($admin)->put(route('inventory.update', $stock), [
            'quantity' => '5.500',
            'reason' => 'Hasil stok opname',
        ])->assertRedirect(route('inventory.index', ['branch_id' => $branch->id]));

        $this->assertDatabaseHas('branch_stocks', [
            'id' => $stock->id,
            'quantity' => '5.500',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'user_id' => $admin->id,
            'movement_type' => 'adjustment',
            'quantity_change' => '3.500',
            'quantity_before' => '2.000',
            'quantity_after' => '5.500',
            'reason' => 'Hasil stok opname',
        ]);

        $this->get(route('inventory.index', ['branch_id' => $branch->id]))->assertOk();
        $this->get(route('inventory.edit', $stock))->assertOk();
    }

    public function test_stock_cannot_be_adjusted_below_zero(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_super_admin' => true])->save();
        $branch = Branch::create(['code' => 'CB-001', 'name' => 'Cabang Pusat']);
        $category = Category::create(['name' => 'Minuman']);
        $product = Product::create([
            'category_id' => $category->id,
            'sku' => 'MIN-001',
            'name' => 'Air Mineral',
            'unit' => 'botol',
            'cost_price' => 2000,
            'sale_price' => 3500,
        ]);
        $stock = BranchStock::create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->actingAs($admin)->put(route('inventory.update', $stock), [
            'quantity' => '-1',
            'reason' => 'Tes stok negatif',
        ])->assertSessionHasErrors('quantity');

        $this->assertSame('2.000', $stock->fresh()->quantity);
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_regular_users_cannot_view_inventory(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('inventory.index'))
            ->assertForbidden();
    }

    public function test_branch_manager_can_only_view_and_adjust_assigned_stock(): void
    {
        $manager = User::factory()->create();
        $assignedBranch = Branch::create(['code' => 'CB-001', 'name' => 'Cabang Kelola']);
        $otherBranch = Branch::create(['code' => 'CB-002', 'name' => 'Cabang Lain']);
        $manager->branches()->attach($assignedBranch, ['role' => 'manager']);
        $category = Category::create(['name' => 'Minuman']);
        $product = Product::create([
            'category_id' => $category->id,
            'sku' => 'AIR-001',
            'name' => 'Air Mineral',
            'unit' => 'botol',
            'cost_price' => 1000,
            'sale_price' => 2000,
        ]);
        $stock = BranchStock::create([
            'branch_id' => $assignedBranch->id,
            'product_id' => $product->id,
            'quantity' => 4,
        ]);
        $otherStock = BranchStock::create([
            'branch_id' => $otherBranch->id,
            'product_id' => $product->id,
            'quantity' => 9,
        ]);

        $this->actingAs($manager)->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('Cabang Kelola')
            ->assertDontSee('Cabang Lain');
        $this->get(route('inventory.edit', $otherStock))->assertForbidden();
        $this->put(route('inventory.update', $otherStock), [
            'quantity' => '0',
            'reason' => 'Tidak diizinkan',
        ])->assertForbidden();

        $this->put(route('inventory.update', $stock), [
            'quantity' => '5',
            'reason' => 'Stok opname',
        ])->assertRedirect();

        $this->assertSame('9.000', $otherStock->fresh()->quantity);
    }
}
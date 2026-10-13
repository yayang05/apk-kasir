<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseReceivingTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_manager_can_receive_purchase_and_increase_stock(): void
    {
        [$manager, $branch, $product, $stock, $supplier] = $this->createManagerWithProduct();

        $this->actingAs($manager)->post(route('purchases.store'), [
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'supplier_invoice_no' => 'INV-SUP-001',
            'items' => [
                ['product_id' => $product->id, 'quantity' => '4', 'unit_cost' => '1800.00'],
            ],
        ])->assertRedirect();

        $purchase = Purchase::query()->firstOrFail();
        $this->assertSame('7200.00', $purchase->total);
        $this->assertSame('6.000', $stock->fresh()->quantity);
        $this->assertDatabaseHas('purchase_items', [
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => '4.000',
            'unit_cost' => '1800.00',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'user_id' => $manager->id,
            'movement_type' => 'purchase',
            'quantity_change' => '4.000',
            'quantity_before' => '2.000',
            'quantity_after' => '6.000',
        ]);

        $this->get(route('purchases.index', ['branch_id' => $branch->id]))->assertOk();
        $this->get(route('purchases.create', ['branch_id' => $branch->id]))
            ->assertOk()
            ->assertSee('Persediaan cabang')
            ->assertSee('otomatis masuk ke stok cabang');
        $this->get(route('purchases.show', $purchase))->assertOk();

        $branch->update(['is_active' => false]);
        $this->get(route('purchases.show', $purchase))->assertOk();
    }

    public function test_cashier_cannot_receive_purchase(): void
    {
        [$manager, $branch, $product, , $supplier] = $this->createManagerWithProduct();
        $cashier = User::factory()->create();
        $cashier->branches()->attach($branch, ['role' => 'cashier']);

        $this->actingAs($cashier)->get(route('purchases.index'))->assertForbidden();
        $this->post(route('purchases.store'), [
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => '1', 'unit_cost' => '1800'],
            ],
        ])->assertForbidden();

        $this->assertSame(0, Purchase::query()->count());
    }

    private function createManagerWithProduct(): array
    {
        $manager = User::factory()->create();
        $branch = Branch::create(['code' => 'CB-001', 'name' => 'Cabang Pusat']);
        $manager->branches()->attach($branch, ['role' => 'manager']);
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
        $supplier = Supplier::create([
            'code' => 'SUP-001',
            'name' => 'Supplier Utama',
        ]);

        return [$manager, $branch, $product, $stock, $supplier];
    }
}
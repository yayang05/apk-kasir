<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_checkout_and_stock_is_reduced_with_audit(): void
    {
        [$user, $branch, $product, $stock, $cashSession] = $this->createCashierWithStock(5);

        $this->actingAs($user)->post(route('sales.store'), [
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'paid_amount' => '10000',
            'items' => [
                ['product_id' => $product->id, 'quantity' => '2'],
            ],
        ])->assertRedirect();

        $sale = Sale::query()->firstOrFail();
        $this->assertSame('7000.00', $sale->grand_total);
        $this->assertSame('3000.00', $sale->change_amount);
        $this->assertSame($cashSession->id, $sale->cash_session_id);
        $this->assertSame('3.000', $stock->fresh()->quantity);
        $this->assertDatabaseHas('sale_payments', [
            'sale_id' => $sale->id,
            'cash_session_id' => $cashSession->id,
            'payment_method' => 'cash',
            'amount' => '7000.00',
            'amount_tendered' => '10000.00',
            'change_amount' => '3000.00',
        ]);
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => '2.000',
            'unit_price' => '3500.00',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'movement_type' => 'sale',
            'quantity_change' => '-2.000',
            'quantity_before' => '5.000',
            'quantity_after' => '3.000',
        ]);

        $this->get(route('sales.create', ['branch_id' => $branch->id]))->assertOk();
        $this->get(route('sales.index', ['branch_id' => $branch->id]))->assertOk();
        $this->get(route('sales.show', $sale))->assertOk();

        $branch->update(['is_active' => false]);
        $this->get(route('sales.show', $sale))->assertOk();
    }

    public function test_checkout_rejects_insufficient_stock_without_partial_writes(): void
    {
        [$user, $branch, $product, $stock] = $this->createCashierWithStock(1);

        $this->actingAs($user)->post(route('sales.store'), [
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'paid_amount' => '10000',
            'items' => [
                ['product_id' => $product->id, 'quantity' => '2'],
            ],
        ])->assertSessionHasErrors('items');

        $this->assertSame('1.000', $stock->fresh()->quantity);
        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_cashier_cannot_sell_from_an_unassigned_branch(): void
    {
        [$user, $branch, $product] = $this->createCashierWithStock(5);
        $otherBranch = Branch::create(['code' => 'CB-002', 'name' => 'Cabang Lain']);

        $this->actingAs($user)->post(route('sales.store'), [
            'branch_id' => $otherBranch->id,
            'payment_method' => 'cash',
            'paid_amount' => '3500',
            'items' => [
                ['product_id' => $product->id, 'quantity' => '1'],
            ],
        ])->assertForbidden();

        $this->assertSame(0, Sale::query()->count());
    }

    public function test_checkout_requires_an_open_cash_session(): void
    {
        [$user, $branch, $product, $stock, $cashSession] = $this->createCashierWithStock(5);
        $cashSession->delete();

        $this->actingAs($user)->post(route('sales.store'), [
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'paid_amount' => '3500',
            'items' => [
                ['product_id' => $product->id, 'quantity' => '1'],
            ],
        ])->assertSessionHasErrors('branch_id');

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame('5.000', $stock->fresh()->quantity);
    }

    public function test_return_restocks_items_and_cannot_exceed_sold_quantity(): void
    {
        [$user, $branch, $product, $stock, $cashSession] = $this->createCashierWithStock(5);
        $sale = Sale::create([
            'invoice_no' => 'TRX-RETURN-001',
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'subtotal' => '7000.00',
            'grand_total' => '7000.00',
            'paid_amount' => '7000.00',
            'change_amount' => '0.00',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);
        SalePayment::create([
            'sale_id' => $sale->id,
            'user_id' => $user->id,
            'cash_session_id' => $cashSession?->id,
            'payment_method' => 'cash',
            'amount' => '7000.00',
            'amount_tendered' => '7000.00',
            'change_amount' => '0.00',
            'paid_at' => now(),
        ]);
        $item = $sale->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit' => $product->unit,
            'quantity' => '2.000',
            'unit_price' => '3500.00',
            'unit_cost' => '2000.00',
            'line_total' => '7000.00',
        ]);
        $stock->update(['quantity' => '3.000']);

        $this->actingAs($user)->post(route('sales.returns.store', $sale), [
            'reason' => 'Barang rusak',
            'refund_method' => 'cash',
            'items' => [$item->id => '1'],
        ])->assertRedirect(route('sales.show', $sale));

        $this->assertSame('4.000', $stock->fresh()->quantity);
        $this->assertDatabaseHas('sale_returns', ['sale_id' => $sale->id, 'total' => '3500.00']);
        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'return', 'quantity_change' => '1.000']);

        $this->actingAs($user)->post(route('sales.returns.store', $sale), [
            'reason' => 'Retur kedua',
            'refund_method' => 'cash',
            'items' => [$item->id => '2'],
        ])->assertSessionHasErrors('items');
        $this->assertSame('4.000', $stock->fresh()->quantity);
    }

    private function createCashierWithStock(int $quantity): array
    {
        $user = User::factory()->create();
        $branch = Branch::create(['code' => 'CB-001', 'name' => 'Cabang Pusat']);
        $user->branches()->attach($branch, ['role' => 'cashier']);
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
            'quantity' => $quantity,
        ]);
        $cashSession = CashSession::create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'opening_cash' => '10000.00',
            'opened_at' => now(),
        ]);

        return [$user, $branch, $product, $stock, $cashSession];
    }
}

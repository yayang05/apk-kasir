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

class CreditPaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_sale_can_be_paid_in_parts_by_transfer_and_cash(): void
    {
        [$cashier, $branch, $product, $stock, $session] = $this->createCashierWithStock();

        $this->actingAs($cashier)->post(route('sales.store'), [
            'branch_id' => $branch->id,
            'payment_method' => 'credit',
            'paid_amount' => '1000',
            'customer_name' => 'Pelanggan Kredit',
            'customer_phone' => '081234567890',
            'items' => [['product_id' => $product->id, 'quantity' => '2']],
        ])->assertRedirect();

        $sale = Sale::query()->firstOrFail();
        $this->assertSame('partial', $sale->payment_status);
        $this->assertSame('Pelanggan Kredit', $sale->customer_name);
        $this->assertSame(6000.0, $sale->balance_due);
        $this->assertDatabaseHas('sale_payments', [
            'sale_id' => $sale->id,
            'payment_method' => 'cash',
            'amount' => '1000.00',
            'cash_session_id' => $session->id,
        ]);
        $this->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee('Pelanggan Kredit')
            ->assertSee('Catat Pelunasan');

        $this->post(route('sales.payments.store', $sale), [
            'payment_method' => 'transfer',
            'amount' => '2000',
            'reference' => 'TRF-001',
        ])->assertRedirect(route('sales.show', $sale));

        $this->assertSame('partial', $sale->fresh()->payment_status);
        $this->assertSame(4000.0, $sale->fresh()->balance_due);

        $this->post(route('sales.payments.store', $sale), [
            'payment_method' => 'cash',
            'amount' => '4000',
        ])->assertRedirect(route('sales.show', $sale));

        $sale->refresh();
        $this->assertSame('paid', $sale->payment_status);
        $this->assertSame(0.0, $sale->balance_due);
        $this->get(route('sales.show', $sale))->assertOk()->assertDontSee('Catat Pelunasan');
        $this->assertDatabaseHas('sale_payments', [
            'sale_id' => $sale->id,
            'payment_method' => 'transfer',
            'amount' => '2000.00',
            'reference' => 'TRF-001',
            'cash_session_id' => null,
        ]);
        $this->assertSame('10.000', $stock->fresh()->quantity);

        $this->actingAs($cashier)->patch(route('cash-sessions.close', $session), [
            'closing_cash' => '15000',
        ])->assertRedirect(route('cash-sessions.index'));
        $this->assertSame('15000.00', $session->fresh()->expected_cash);
    }

    public function test_transfer_sale_requires_exact_total_and_reference(): void
    {
        [$cashier, $branch, $product, $stock] = $this->createCashierWithStock();

        $this->actingAs($cashier)->post(route('sales.store'), [
            'branch_id' => $branch->id,
            'payment_method' => 'transfer',
            'paid_amount' => '7000',
            'items' => [['product_id' => $product->id, 'quantity' => '2']],
        ])->assertSessionHasErrors('payment_reference');

        $this->post(route('sales.store'), [
            'branch_id' => $branch->id,
            'payment_method' => 'transfer',
            'paid_amount' => '8000',
            'payment_reference' => 'TRF-OVER',
            'items' => [['product_id' => $product->id, 'quantity' => '2']],
        ])->assertSessionHasErrors('paid_amount');

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame('12.000', $stock->fresh()->quantity);
        $this->assertSame(0, SalePayment::query()->count());
    }

    public function test_credit_sale_requires_customer_name(): void
    {
        [$cashier, $branch, $product, $stock] = $this->createCashierWithStock();

        $this->actingAs($cashier)->post(route('sales.store'), [
            'branch_id' => $branch->id,
            'payment_method' => 'credit',
            'paid_amount' => '0',
            'items' => [['product_id' => $product->id, 'quantity' => '1']],
        ])->assertSessionHasErrors('customer_name');

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame('12.000', $stock->fresh()->quantity);
    }

    public function test_credit_payment_cannot_exceed_balance_or_use_unassigned_branch(): void
    {
        [$cashier, $branch, $product] = $this->createCashierWithStock();
        $this->actingAs($cashier)->post(route('sales.store'), [
            'branch_id' => $branch->id,
            'payment_method' => 'credit',
            'paid_amount' => '0',
            'customer_name' => 'Pelanggan Kredit',
            'items' => [['product_id' => $product->id, 'quantity' => '1']],
        ])->assertRedirect();
        $sale = Sale::query()->firstOrFail();

        $this->post(route('sales.payments.store', $sale), [
            'payment_method' => 'cash',
            'amount' => '4000',
        ])->assertSessionHasErrors('amount');

        $otherCashier = User::factory()->create();
        $this->actingAs($otherCashier)->post(route('sales.payments.store', $sale), [
            'payment_method' => 'transfer',
            'amount' => '1',
            'reference' => 'TRF-002',
        ])->assertForbidden();

        $this->assertSame('unpaid', $sale->fresh()->payment_status);
        $this->assertSame(0, SalePayment::query()->count());
    }

    private function createCashierWithStock(): array
    {
        $cashier = User::factory()->create();
        $branch = Branch::create(['code' => 'CB-001', 'name' => 'Cabang Kredit']);
        $cashier->branches()->attach($branch, ['role' => 'cashier']);
        $category = Category::create(['name' => 'Minuman']);
        $product = Product::create([
            'category_id' => $category->id,
            'sku' => 'AIR-001',
            'name' => 'Air Mineral',
            'unit' => 'botol',
            'cost_price' => 2000,
            'sale_price' => 3500,
        ]);
        $stock = BranchStock::create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'quantity' => 12,
        ]);
        $session = CashSession::create([
            'branch_id' => $branch->id,
            'user_id' => $cashier->id,
            'opening_cash' => '10000.00',
            'opened_at' => now(),
        ]);

        return [$cashier, $branch, $product, $stock, $session];
    }
}
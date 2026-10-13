<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_sees_sales_and_gross_profit_only_for_assigned_branches(): void
    {
        $manager = User::factory()->create();
        $branch = Branch::create(['code' => 'CB-001', 'name' => 'Cabang Pusat']);
        $otherBranch = Branch::create(['code' => 'CB-002', 'name' => 'Cabang Lain']);
        $manager->branches()->attach($branch, ['role' => 'manager']);
        $category = \App\Models\Category::create(['name' => 'Minuman']);
        $product = Product::create([
            'category_id' => $category->id,
            'sku' => 'MIN-001',
            'name' => 'Air Mineral',
            'unit' => 'botol',
            'cost_price' => 2000,
            'sale_price' => 3500,
        ]);

        $sale = Sale::create([
            'invoice_no' => 'TRX-REPORT-001',
            'branch_id' => $branch->id,
            'user_id' => $manager->id,
            'subtotal' => '7000.00',
            'grand_total' => '7000.00',
            'paid_amount' => '10000.00',
            'change_amount' => '3000.00',
            'payment_method' => 'cash',
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'product_name' => 'Air Mineral',
            'sku' => 'MIN-001',
            'unit' => 'botol',
            'quantity' => '2.000',
            'unit_price' => '3500.00',
            'unit_cost' => '2000.00',
            'line_total' => '7000.00',
        ]);
        Sale::create([
            'invoice_no' => 'TRX-REPORT-002',
            'branch_id' => $otherBranch->id,
            'user_id' => $manager->id,
            'subtotal' => '9000.00',
            'grand_total' => '9000.00',
            'paid_amount' => '9000.00',
            'change_amount' => '0.00',
            'payment_method' => 'cash',
        ]);

        $this->actingAs($manager)->get(route('reports.index'))
            ->assertOk()
            ->assertViewHas('summary', function (array $summary) {
                $this->assertSame(1, $summary['transaction_count']);
                $this->assertSame(7000.0, $summary['revenue']);
                $this->assertSame(4000.0, $summary['cost_of_goods_sold']);
                $this->assertSame(3000.0, $summary['gross_profit']);

                return true;
            })
            ->assertSee('Air Mineral');

        $this->get(route('reports.index', ['branch_id' => $otherBranch->id]))->assertForbidden();
    }

    public function test_cashier_cannot_view_financial_reports(): void
    {
        $cashier = User::factory()->create();
        $branch = Branch::create(['code' => 'CB-001', 'name' => 'Cabang Pusat']);
        $cashier->branches()->attach($branch, ['role' => 'cashier']);

        $this->actingAs($cashier)->get(route('reports.index'))->assertForbidden();
    }

    public function test_report_rejects_a_date_range_with_end_before_start(): void
    {
        $manager = User::factory()->create();
        $branch = Branch::create(['code' => 'CB-001', 'name' => 'Cabang Pusat']);
        $manager->branches()->attach($branch, ['role' => 'manager']);

        $this->actingAs($manager)->get(route('reports.index', [
            'date_from' => '2026-10-06',
            'date_to' => '2026-10-05',
        ]))->assertSessionHasErrors('date_to');
    }

    public function test_reports_separate_cash_transfer_credit_sales_and_open_balances(): void
    {
        $manager = User::factory()->create();
        $branch = Branch::create(['code' => 'CB-001', 'name' => 'Cabang Pusat']);
        $manager->branches()->attach($branch, ['role' => 'manager']);
        $cashSale = $this->createSale($manager, $branch, 'TRX-CASH-001', '5000.00', 'cash');
        $cashSale->payments()->create([
            'user_id' => $manager->id,
            'payment_method' => 'cash',
            'amount' => '5000.00',
            'amount_tendered' => '5000.00',
            'change_amount' => '0.00',
            'paid_at' => now(),
        ]);

        $transferSale = $this->createSale($manager, $branch, 'TRX-TRANSFER-001', '7000.00', 'transfer');
        $transferSale->payments()->create([
            'user_id' => $manager->id,
            'payment_method' => 'transfer',
            'amount' => '7000.00',
            'amount_tendered' => '7000.00',
            'change_amount' => '0.00',
            'reference' => 'TRF-REPORT-001',
            'paid_at' => now(),
        ]);

        $creditSale = $this->createSale($manager, $branch, 'TRX-CREDIT-001', '10000.00', 'credit');
        $creditSale->payments()->create([
            'user_id' => $manager->id,
            'payment_method' => 'cash',
            'amount' => '1000.00',
            'amount_tendered' => '1000.00',
            'change_amount' => '0.00',
            'paid_at' => now(),
        ]);

        $this->actingAs($manager)->get(route('reports.index'))
            ->assertOk()
            ->assertViewHas('paymentSummary', function (array $summary) {
                $this->assertSame(6000.0, $summary['cash_collected']);
                $this->assertSame(7000.0, $summary['transfer_collected']);
                $this->assertSame(10000.0, $summary['credit_sales']);
                $this->assertSame(1, $summary['open_credit_count']);
                $this->assertSame(9000.0, $summary['credit_outstanding']);

                return true;
            })
            ->assertSee('TRX-CREDIT-001')
            ->assertSee('Saldo Piutang');
    }

    public function test_manager_can_export_sales_from_assigned_branch_only(): void
    {
        $manager = User::factory()->create();
        $branch = Branch::create(['code' => 'CB-001', 'name' => 'Cabang Pusat']);
        $otherBranch = Branch::create(['code' => 'CB-002', 'name' => 'Cabang Lain']);
        $manager->branches()->attach($branch, ['role' => 'manager']);
        $this->createSale($manager, $branch, 'TRX-EXPORT-001', '12500.00', 'cash');
        $this->createSale($manager, $otherBranch, 'TRX-EXPORT-002', '9000.00', 'cash');

        $response = $this->actingAs($manager)->get(route('reports.export'));

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('TRX-EXPORT-001', $csv);
        $this->assertStringNotContainsString('TRX-EXPORT-002', $csv);
    }

    public function test_report_includes_daily_cashier_and_purchase_summaries(): void
    {
        $manager = User::factory()->create(['name' => 'Kasir Uji']);
        $branch = Branch::create(['code' => 'CB-001', 'name' => 'Cabang Pusat']);
        $manager->branches()->attach($branch, ['role' => 'manager']);
        $this->createSale($manager, $branch, 'TRX-DETAIL-001', '12500.00', 'cash');

        $supplier = \App\Models\Supplier::create(['code' => 'SUP-001', 'name' => 'Supplier Uji']);
        \App\Models\Purchase::create([
            'purchase_no' => 'PO-DETAIL-001',
            'supplier_id' => $supplier->id,
            'branch_id' => $branch->id,
            'user_id' => $manager->id,
            'total' => '5000.00',
            'received_at' => now(),
        ]);

        $this->actingAs($manager)->get(route('reports.index'))
            ->assertOk()
            ->assertViewHas('purchaseSummary', fn (array $summary) => $summary['count'] === 1 && $summary['total'] === 5000.0)
            ->assertViewHas('dailySales', fn ($sales) => $sales->count() === 1)
            ->assertViewHas('cashierSummaries', fn ($cashiers) => $cashiers->first()->cashier_name === 'Kasir Uji');
    }

    private function createSale(User $user, Branch $branch, string $invoice, string $total, string $method): Sale
    {
        return Sale::create([
            'invoice_no' => $invoice,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'subtotal' => $total,
            'grand_total' => $total,
            'paid_amount' => $total,
            'change_amount' => '0.00',
            'payment_method' => $method,
            'payment_status' => $method === 'credit' ? 'partial' : 'paid',
            'customer_name' => $method === 'credit' ? 'Pelanggan Uji' : null,
        ]);
    }
}

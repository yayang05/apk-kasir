<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Sample data seeding is disabled in production.');
        }

        DB::transaction(function () {
            $branches = collect([
                ['code' => 'DEMO-PUSAT', 'name' => 'Demo - Cabang Pusat', 'address' => 'Jl. Merdeka No. 10', 'phone' => '0215550101'],
                ['code' => 'DEMO-TIMUR', 'name' => 'Demo - Cabang Timur', 'address' => 'Jl. Melati No. 25', 'phone' => '0215550202'],
            ])->mapWithKeys(fn (array $attributes) => [
                $attributes['code'] => Branch::firstOrCreate(['code' => $attributes['code']], $attributes),
            ]);

            $categories = collect([
                'Minuman',
                'Makanan Instan',
                'Kebutuhan Rumah',
                'Camilan',
            ])->mapWithKeys(fn (string $name) => [
                $name => Category::firstOrCreate(['name' => $name]),
            ]);

            $productDefinitions = [
                'AIR-001' => ['category' => 'Minuman', 'barcode' => '899100000001', 'name' => 'Air Mineral 600 ml', 'unit' => 'botol', 'cost' => '2200.00', 'price' => '3500.00'],
                'TEH-001' => ['category' => 'Minuman', 'barcode' => '899100000002', 'name' => 'Teh Siap Minum 350 ml', 'unit' => 'botol', 'cost' => '3200.00', 'price' => '5000.00'],
                'MIE-001' => ['category' => 'Makanan Instan', 'barcode' => '899100000003', 'name' => 'Mi Goreng Instan', 'unit' => 'pcs', 'cost' => '2500.00', 'price' => '3500.00'],
                'MIE-002' => ['category' => 'Makanan Instan', 'barcode' => '899100000004', 'name' => 'Mi Kuah Instan', 'unit' => 'pcs', 'cost' => '2400.00', 'price' => '3500.00'],
                'GUL-001' => ['category' => 'Kebutuhan Rumah', 'barcode' => '899100000005', 'name' => 'Gula Pasir 1 kg', 'unit' => 'kg', 'cost' => '14500.00', 'price' => '18000.00'],
                'MIN-001' => ['category' => 'Kebutuhan Rumah', 'barcode' => '899100000006', 'name' => 'Minyak Goreng 1 L', 'unit' => 'botol', 'cost' => '15500.00', 'price' => '18500.00'],
                'SNK-001' => ['category' => 'Camilan', 'barcode' => '899100000007', 'name' => 'Biskuit Cokelat', 'unit' => 'bungkus', 'cost' => '5200.00', 'price' => '8000.00'],
            ];

            $products = collect($productDefinitions)->mapWithKeys(function (array $attributes, string $sku) use ($categories) {
                return [$sku => Product::firstOrCreate(['sku' => $sku], [
                    'category_id' => $categories[$attributes['category']]->id,
                    'barcode' => $attributes['barcode'],
                    'name' => $attributes['name'],
                    'unit' => $attributes['unit'],
                    'cost_price' => $attributes['cost'],
                    'sale_price' => $attributes['price'],
                ])];
            });

            $supplier = Supplier::firstOrCreate(['code' => 'DEMO-SUP-001'], [
                'name' => 'Demo - Pemasok Nusantara',
                'contact_person' => 'Rina Putri',
                'email' => 'pemasok@example.test',
                'phone' => '0215550303',
                'address' => 'Jakarta',
            ]);

            $manager = $this->demoUser('manager.demo@example.test', 'Manajer Demo');
            $centralCashier = $this->demoUser('kasir.pusat@example.test', 'Kasir Demo Pusat');
            $eastCashier = $this->demoUser('kasir.timur@example.test', 'Kasir Demo Timur');

            $this->assignRole($manager, $branches['DEMO-PUSAT'], 'manager');
            $this->assignRole($manager, $branches['DEMO-TIMUR'], 'manager');
            $this->assignRole($centralCashier, $branches['DEMO-PUSAT'], 'cashier');
            $this->assignRole($eastCashier, $branches['DEMO-TIMUR'], 'cashier');

            foreach ($branches as $branch) {
                foreach ($products as $product) {
                    BranchStock::firstOrCreate(
                        ['branch_id' => $branch->id, 'product_id' => $product->id],
                        ['quantity' => '0.000']
                    );
                }
            }

            $this->receiveSamplePurchase(
                'PB-DEMO-PUSAT-001',
                $branches['DEMO-PUSAT'],
                $supplier,
                $manager,
                $products,
                ['AIR-001' => [40, '2200.00'], 'TEH-001' => [30, '3200.00'], 'MIE-001' => [50, '2500.00'], 'MIE-002' => [30, '2400.00'], 'GUL-001' => [12, '14500.00'], 'MIN-001' => [15, '15500.00'], 'SNK-001' => [24, '5200.00']],
                'FAKTUR-DEMO-001'
            );
            $this->receiveSamplePurchase(
                'PB-DEMO-TIMUR-001',
                $branches['DEMO-TIMUR'],
                $supplier,
                $manager,
                $products,
                ['AIR-001' => [24, '2200.00'], 'TEH-001' => [18, '3200.00'], 'MIE-001' => [30, '2500.00'], 'MIE-002' => [18, '2400.00'], 'GUL-001' => [8, '14500.00'], 'MIN-001' => [10, '15500.00'], 'SNK-001' => [14, '5200.00']],
                'FAKTUR-DEMO-002'
            );

            $centralSession = $this->sessionFor($centralCashier, $branches['DEMO-PUSAT'], '50000.00');
            $eastSession = $this->sessionFor($eastCashier, $branches['DEMO-TIMUR'], '100000.00');

            $this->recordSampleSale('TRX-DEMO-PUSAT-001', $centralSession, $products, [
                'AIR-001' => 2,
                'MIE-001' => 2,
                'SNK-001' => 1,
            ]);
            $this->recordSampleSale('TRX-DEMO-PUSAT-002', $centralSession, $products, [
                'TEH-001' => 2,
                'GUL-001' => 1,
            ]);
            $this->recordSampleSale('TRX-DEMO-TIMUR-001', $eastSession, $products, [
                'AIR-001' => 3,
                'MIE-002' => 2,
                'MIN-001' => 1,
            ]);
            $this->recordSampleSale(
                'TRX-DEMO-PUSAT-TRANSFER-001',
                $centralSession,
                $products,
                ['TEH-001' => 1],
                paymentMethod: 'transfer',
                paymentReference: 'DEMO-TRF-001'
            );
            $this->recordSampleSale(
                'TRX-DEMO-PUSAT-KREDIT-001',
                $centralSession,
                $products,
                ['AIR-001' => 1, 'MIE-001' => 1],
                paymentMethod: 'credit',
                amountPaidCents: $this->toMinorUnits('2000.00'),
                customerName: 'Pelanggan Kredit Demo',
                customerPhone: '081234560001'
            );
            $this->ensureLowStockSample($branches['DEMO-TIMUR'], $products['SNK-001'], $manager);

            if (! $eastSession->closed_at) {
                $salesCents = (int) Sale::query()
                    ->where('cash_session_id', $eastSession->id)
                    ->sum(DB::raw('ROUND(grand_total * 100)'));
                $expectedCents = $this->toMinorUnits($eastSession->opening_cash) + $salesCents;
                $closingCents = max(0, $expectedCents - 500);
                $eastSession->update([
                    'expected_cash' => number_format($expectedCents / 100, 2, '.', ''),
                    'closing_cash' => number_format($closingCents / 100, 2, '.', ''),
                    'difference' => number_format(($closingCents - $expectedCents) / 100, 2, '.', ''),
                    'closed_at' => now()->subMinute(),
                ]);
            }
        });
    }

    private function demoUser(string $email, string $name): User
    {
        $user = User::firstOrCreate(['email' => $email], [
            'name' => $name,
            'password' => 'DemoKasir2026!',
        ]);

        if ($user->isSuperAdmin()) {
            throw new \RuntimeException("Demo account {$email} is unexpectedly a superadmin.");
        }

        return $user;
    }

    private function assignRole(User $user, Branch $branch, string $role): void
    {
        $user->branches()->syncWithoutDetaching([$branch->id => ['role' => $role]]);
        $user->branches()->updateExistingPivot($branch->id, ['role' => $role]);
    }

    private function receiveSamplePurchase(
        string $purchaseNo,
        Branch $branch,
        Supplier $supplier,
        User $user,
        $products,
        array $lines,
        string $supplierInvoiceNo
    ): void {
        if (Purchase::query()->where('purchase_no', $purchaseNo)->exists()) {
            return;
        }

        $preparedLines = [];
        $totalCents = 0;

        foreach ($lines as $sku => [$quantity, $unitCost]) {
            $product = $products[$sku];
            $unitCostCents = $this->toMinorUnits($unitCost);
            $lineTotalCents = $unitCostCents * $quantity;
            $totalCents += $lineTotalCents;
            $preparedLines[] = [$product, $quantity, $unitCostCents, $lineTotalCents];
        }

        $purchase = Purchase::create([
            'purchase_no' => $purchaseNo,
            'supplier_id' => $supplier->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'supplier_invoice_no' => $supplierInvoiceNo,
            'total' => number_format($totalCents / 100, 2, '.', ''),
            'received_at' => now()->subDay(),
        ]);

        foreach ($preparedLines as [$product, $quantity, $unitCostCents, $lineTotalCents]) {
            $stock = BranchStock::query()
                ->where('branch_id', $branch->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();
            $before = number_format((float) $stock->quantity, 3, '.', '');
            $after = number_format((float) $before + $quantity, 3, '.', '');
            $unitCost = number_format($unitCostCents / 100, 2, '.', '');

            $purchase->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'unit' => $product->unit,
                'quantity' => number_format($quantity, 3, '.', ''),
                'unit_cost' => $unitCost,
                'line_total' => number_format($lineTotalCents / 100, 2, '.', ''),
            ]);
            $stock->update(['quantity' => $after]);
            StockMovement::create([
                'branch_id' => $branch->id,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'movement_type' => 'purchase',
                'quantity_change' => number_format($quantity, 3, '.', ''),
                'quantity_before' => $before,
                'quantity_after' => $after,
                'reason' => "Demo penerimaan {$purchaseNo}",
            ]);
        }
    }

    private function sessionFor(User $user, Branch $branch, string $openingCash): CashSession
    {
        $session = CashSession::query()
            ->where('user_id', $user->id)
            ->where('branch_id', $branch->id)
            ->whereDate('opened_at', now()->toDateString())
            ->first();

        if ($session) {
            return $session;
        }

        $existingOpenSession = CashSession::query()
            ->where('user_id', $user->id)
            ->whereNull('closed_at')
            ->first();

        if ($existingOpenSession) {
            return $existingOpenSession;
        }

        return CashSession::create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'opening_cash' => $openingCash,
            'opened_at' => now()->startOfDay(),
        ]);
    }

    private function recordSampleSale(
        string $invoiceNo,
        CashSession $session,
        $products,
        array $lines,
        string $paymentMethod = 'cash',
        ?int $amountPaidCents = null,
        ?string $customerName = null,
        ?string $customerPhone = null,
        ?string $paymentReference = null
    ): void {
        if (Sale::query()->where('invoice_no', $invoiceNo)->exists()) {
            return;
        }

        $preparedLines = [];
        $subtotalCents = 0;

        foreach ($lines as $sku => $quantity) {
            $product = $products[$sku];
            $lineTotalCents = $this->toMinorUnits($product->sale_price) * $quantity;
            $subtotalCents += $lineTotalCents;
            $preparedLines[] = [$product, $quantity, $lineTotalCents];
        }

        $amountPaidCents ??= $paymentMethod === 'cash' ? $subtotalCents + 5000 : $subtotalCents;
        $amountAppliedCents = $paymentMethod === 'credit' ? $amountPaidCents : $subtotalCents;
        $changeCents = $paymentMethod === 'cash' ? max(0, $amountPaidCents - $subtotalCents) : 0;
        $paymentStatus = $amountAppliedCents === 0
            ? 'unpaid'
            : ($amountAppliedCents < $subtotalCents ? 'partial' : 'paid');

        $sale = Sale::create([
            'invoice_no' => $invoiceNo,
            'branch_id' => $session->branch_id,
            'user_id' => $session->user_id,
            'cash_session_id' => $session->id,
            'subtotal' => number_format($subtotalCents / 100, 2, '.', ''),
            'grand_total' => number_format($subtotalCents / 100, 2, '.', ''),
            'paid_amount' => number_format($amountPaidCents / 100, 2, '.', ''),
            'change_amount' => number_format($changeCents / 100, 2, '.', ''),
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
        ]);
        $saleDate = now()->subMinutes(10);
        $sale->forceFill(['created_at' => $saleDate, 'updated_at' => $saleDate])->save();

        if ($amountAppliedCents > 0) {
            SalePayment::create([
                'sale_id' => $sale->id,
                'user_id' => $session->user_id,
                'cash_session_id' => $paymentMethod === 'transfer' ? null : $session->id,
                'payment_method' => $paymentMethod === 'credit' ? 'cash' : $paymentMethod,
                'amount' => number_format($amountAppliedCents / 100, 2, '.', ''),
                'amount_tendered' => number_format($amountPaidCents / 100, 2, '.', ''),
                'change_amount' => number_format($changeCents / 100, 2, '.', ''),
                'reference' => $paymentReference,
                'paid_at' => $saleDate,
            ]);
        }

        foreach ($preparedLines as [$product, $quantity, $lineTotalCents]) {
            $stock = BranchStock::query()
                ->where('branch_id', $session->branch_id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();
            $before = number_format((float) $stock->quantity, 3, '.', '');
            $after = number_format((float) $before - $quantity, 3, '.', '');

            $sale->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'unit' => $product->unit,
                'quantity' => number_format($quantity, 3, '.', ''),
                'unit_price' => number_format($this->toMinorUnits($product->sale_price) / 100, 2, '.', ''),
                'unit_cost' => $product->cost_price,
                'line_total' => number_format($lineTotalCents / 100, 2, '.', ''),
            ]);
            $stock->update(['quantity' => $after]);
            StockMovement::create([
                'branch_id' => $session->branch_id,
                'product_id' => $product->id,
                'user_id' => $session->user_id,
                'movement_type' => 'sale',
                'quantity_change' => number_format(-$quantity, 3, '.', ''),
                'quantity_before' => $before,
                'quantity_after' => $after,
                'reason' => "Demo penjualan {$invoiceNo}",
            ]);
        }
    }

    private function ensureLowStockSample(Branch $branch, Product $product, User $user): void
    {
        $reason = 'Demo - contoh stok menipis';
        $movementExists = StockMovement::query()
            ->where('branch_id', $branch->id)
            ->where('product_id', $product->id)
            ->where('reason', $reason)
            ->exists();

        if ($movementExists) {
            return;
        }

        $stock = BranchStock::query()
            ->where('branch_id', $branch->id)
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->firstOrFail();
        $before = number_format((float) $stock->quantity, 3, '.', '');
        $after = '4.000';

        if ($before === $after) {
            return;
        }

        $stock->update(['quantity' => $after]);
        StockMovement::create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'user_id' => $user->id,
            'movement_type' => 'adjustment',
            'quantity_change' => number_format((float) $after - (float) $before, 3, '.', ''),
            'quantity_before' => $before,
            'quantity_after' => $after,
            'reason' => $reason,
        ]);
    }

    private function toMinorUnits(string|int|float $amount): int
    {
        return (int) str_replace('.', '', number_format((float) $amount, 2, '.', ''));
    }
}
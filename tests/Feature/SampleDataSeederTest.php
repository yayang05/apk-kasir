<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\SampleDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SampleDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_data_covers_the_application_and_is_idempotent(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_super_admin' => true])->save();

        app(SampleDataSeeder::class)->run();

        $this->assertSame(2, Branch::query()->count());
        $this->assertSame(4, Category::query()->count());
        $this->assertSame(7, Product::query()->count());
        $this->assertSame(1, Supplier::query()->count());
        $this->assertSame(2, Purchase::query()->count());
        $this->assertSame(5, Sale::query()->count());
        $this->assertSame(5, SalePayment::query()->count());
        $this->assertDatabaseHas('sales', [
            'invoice_no' => 'TRX-DEMO-PUSAT-KREDIT-001',
            'customer_name' => 'Pelanggan Kredit Demo',
            'payment_status' => 'partial',
        ]);
        $this->assertSame(2, CashSession::query()->count());
        $this->assertGreaterThan(0, StockMovement::query()->count());
        $this->assertDatabaseHas('branch_stocks', [
            'branch_id' => Branch::query()->where('code', 'DEMO-TIMUR')->value('id'),
            'product_id' => Product::query()->where('sku', 'SNK-001')->value('id'),
            'quantity' => '4.000',
        ]);
        $this->assertTrue($admin->fresh()->isSuperAdmin());
        $this->assertSame(3, User::query()->where('is_super_admin', false)->count());

        $this->actingAs($admin);
        foreach ([
            'dashboard',
            'branches.index',
            'branches.create',
            'categories.index',
            'categories.create',
            'products.index',
            'products.create',
            'inventory.index',
            'employees.index',
            'employees.create',
            'suppliers.index',
            'suppliers.create',
            'purchases.index',
            'purchases.create',
            'reports.index',
            'cash-sessions.index',
            'sales.create',
            'sales.index',
        ] as $routeName) {
            $this->get(route($routeName))->assertOk();
        }

        $manager = User::query()->where('email', 'manager.demo@example.test')->firstOrFail();
        $this->actingAs($manager)->get(route('reports.index'))->assertOk();
        $this->get(route('dashboard'))->assertOk()->assertSee('Biskuit Cokelat');
        $this->get(route('purchases.index'))->assertOk();

        $cashier = User::query()->where('email', 'kasir.pusat@example.test')->firstOrFail();
        $this->actingAs($cashier)->get(route('sales.create'))->assertOk();
        $this->get(route('cash-sessions.index'))->assertOk();
        $this->get(route('purchases.index'))->assertForbidden();

        app(SampleDataSeeder::class)->run();

        $this->assertSame(2, Branch::query()->count());
        $this->assertSame(7, Product::query()->count());
        $this->assertSame(2, Purchase::query()->count());
        $this->assertSame(5, Sale::query()->count());
        $this->assertSame(2, CashSession::query()->count());
        $this->assertSame(1, StockMovement::query()->where('reason', 'Demo - contoh stok menipis')->count());
    }
}
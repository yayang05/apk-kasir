<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\CashSession;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_settings_and_create_a_private_backup(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();

        $this->actingAs($admin)->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Transparan')
            ->assertSee('theme_background');

        $response = $this->get(route('settings.backup'))
            ->assertOk()
            ->assertHeader('cache-control', 'no-store, private')
            ->assertHeader('content-type', 'application/json');

        $this->assertStringContainsString('kasir-backup-', $response->headers->get('content-disposition'));
        $this->assertCount(1, Storage::disk('local')->files('backups'));
    }

    public function test_product_csv_can_be_exported_and_imported_with_zero_stock_for_each_branch(): void
    {
        $admin = $this->createAdmin();
        $firstBranch = Branch::create(['code' => 'CB-001', 'name' => 'Cabang Satu']);
        $secondBranch = Branch::create(['code' => 'CB-002', 'name' => 'Cabang Dua']);

        $this->actingAs($admin)->post(route('settings.products.import'), [
            'products_csv' => UploadedFile::fake()->createWithContent(
                'products.csv',
                "sku,barcode,name,category,unit,cost_price,sale_price,is_active\nCSV-001,899123,Produk CSV,Minuman,pcs,1200,2500,1\n"
            ),
        ])->assertRedirect(route('settings.index'));

        $product = Product::query()->where('sku', 'CSV-001')->firstOrFail();
        $this->assertSame('Produk CSV', $product->name);
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

        $this->get(route('settings.products.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_product_import_rejects_invalid_data_without_partial_writes(): void
    {
        $admin = $this->createAdmin();
        $branch = Branch::create(['code' => 'CB-001', 'name' => 'Cabang Satu']);

        $this->actingAs($admin)->post(route('settings.products.import'), [
            'products_csv' => UploadedFile::fake()->createWithContent(
                'products.csv',
                "sku,name,category,unit,cost_price,sale_price\nBAD SKU,Produk Rusak,Minuman,pcs,-1,1000\n"
            ),
        ])->assertSessionHasErrors('products_csv');

        $this->assertSame(0, Product::query()->count());
        $this->assertSame(0, Category::query()->count());
        $this->assertSame(0, BranchStock::query()->count());
        $this->assertSame(1, Branch::query()->count());
    }

    public function test_reset_requires_password_and_confirmation_and_preserves_master_data(): void
    {
        $admin = $this->createAdmin();
        $branch = Branch::create(['code' => 'CB-001', 'name' => 'Cabang Satu']);
        $category = Category::create(['name' => 'Minuman']);
        $supplier = Supplier::create(['code' => 'SUP-001', 'name' => 'Pemasok Satu']);
        $product = Product::create([
            'category_id' => $category->id,
            'sku' => 'AIR-001',
            'name' => 'Air Mineral',
            'unit' => 'botol',
            'cost_price' => 1000,
            'sale_price' => 2000,
        ]);
        $stock = BranchStock::create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'quantity' => 12,
        ]);
        $session = CashSession::create([
            'branch_id' => $branch->id,
            'user_id' => $admin->id,
            'opening_cash' => 5000,
            'opened_at' => now(),
        ]);
        $sale = Sale::create([
            'invoice_no' => 'TRX-RESET-001',
            'branch_id' => $branch->id,
            'user_id' => $admin->id,
            'cash_session_id' => $session->id,
            'subtotal' => 2000,
            'grand_total' => 2000,
            'paid_amount' => 2000,
            'change_amount' => 0,
            'payment_method' => 'cash',
        ]);
        $purchase = Purchase::create([
            'purchase_no' => 'PB-RESET-001',
            'supplier_id' => $supplier->id,
            'branch_id' => $branch->id,
            'user_id' => $admin->id,
            'total' => 1000,
            'received_at' => now(),
        ]);
        StockMovement::create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'user_id' => $admin->id,
            'movement_type' => 'adjustment',
            'quantity_change' => 12,
            'quantity_before' => 0,
            'quantity_after' => 12,
            'reason' => 'Seed test',
        ]);

        $this->actingAs($admin)->post(route('settings.reset-operational-data'), [
            'password' => 'wrong-password',
            'confirmation' => 'RESET DATA OPERASIONAL',
        ])->assertSessionHasErrors('password');
        $this->assertSame(1, Sale::query()->count());

        $this->post(route('settings.reset-operational-data'), [
            'password' => 'correct-password',
            'confirmation' => 'hapus semua',
        ])->assertSessionHasErrors('confirmation');

        $this->post(route('settings.reset-operational-data'), [
            'password' => 'correct-password',
            'confirmation' => 'RESET DATA OPERASIONAL',
        ])->assertRedirect(route('settings.index'));

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(0, Purchase::query()->count());
        $this->assertSame(0, CashSession::query()->count());
        $this->assertSame(0, StockMovement::query()->count());
        $this->assertSame(0.0, (float) $stock->fresh()->quantity);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('branches', ['id' => $branch->id]);
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_super_admin' => true]);
    }

    public function test_regular_users_cannot_create_backups_import_or_reset_data(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('settings.index'))
            ->assertOk()
            ->assertDontSee('Backup Database')
            ->assertDontSee('Reset Data Operasional');
        $this->get(route('settings.backup'))->assertForbidden();
        $this->get(route('settings.products.export'))->assertForbidden();
        $this->post(route('settings.products.import'))->assertForbidden();
        $this->post(route('settings.reset-operational-data'))->assertForbidden();
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create(['password' => 'correct-password']);
        $admin->forceFill(['is_super_admin' => true])->save();

        return $admin;
    }
}
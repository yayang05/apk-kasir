<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_manage_supplier_records(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_super_admin' => true])->save();

        $this->actingAs($admin)->get(route('suppliers.index'))->assertOk();
        $this->get(route('suppliers.create'))->assertOk();

        $this->post(route('suppliers.store'), [
            'code' => 'SUP-001',
            'name' => 'Pemasok Utama',
            'contact_person' => 'Budi',
            'email' => 'budi@supplier.test',
            'phone' => '021123456',
            'address' => 'Jakarta',
        ])->assertRedirect(route('suppliers.index'));

        $supplier = Supplier::query()->firstOrFail();
        $this->get(route('suppliers.edit', $supplier))->assertOk();

        $this->put(route('suppliers.update', $supplier), [
            'code' => 'SUP-001',
            'name' => 'Pemasok Utama Baru',
        ])->assertRedirect(route('suppliers.index'));

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'Pemasok Utama Baru',
        ]);
    }

    public function test_regular_users_cannot_manage_suppliers(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('suppliers.index'))
            ->assertForbidden();
    }
}
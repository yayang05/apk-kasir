<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_assign_employee_roles_across_branches(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_super_admin' => true])->save();
        $firstBranch = Branch::create(['code' => 'CB-001', 'name' => 'Cabang Pusat']);
        $secondBranch = Branch::create(['code' => 'CB-002', 'name' => 'Cabang Timur']);

        $this->actingAs($admin)->post(route('employees.store'), [
            'name' => 'Siti Kasir',
            'email' => 'siti@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'branch_roles' => [
                $firstBranch->id => ['enabled' => '1', 'role' => 'manager'],
                $secondBranch->id => ['enabled' => '1', 'role' => 'cashier'],
            ],
            'is_super_admin' => '1',
        ])->assertRedirect(route('employees.index'));

        $employee = User::query()->where('email', 'siti@example.com')->firstOrFail();
        $this->assertFalse($employee->isSuperAdmin());
        $this->assertDatabaseHas('branch_user', [
            'user_id' => $employee->id,
            'branch_id' => $firstBranch->id,
            'role' => 'manager',
        ]);
        $this->assertDatabaseHas('branch_user', [
            'user_id' => $employee->id,
            'branch_id' => $secondBranch->id,
            'role' => 'cashier',
        ]);

        $this->get(route('employees.index'))->assertOk();
        $this->get(route('employees.edit', $employee))->assertOk();
    }

    public function test_non_admin_users_cannot_manage_employees(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('employees.index'))
            ->assertForbidden();
    }

    public function test_editing_an_employee_preserves_an_existing_inactive_branch_assignment(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_super_admin' => true])->save();
        $branch = Branch::create(['code' => 'CB-001', 'name' => 'Cabang Pusat', 'is_active' => false]);
        $employee = User::factory()->create();
        $employee->branches()->attach($branch, ['role' => 'cashier']);

        $this->actingAs($admin)->get(route('employees.edit', $employee))->assertOk();
        $this->put(route('employees.update', $employee), [
            'name' => 'Pegawai Diperbarui',
            'email' => $employee->email,
            'branch_roles' => [
                $branch->id => ['enabled' => '1', 'role' => 'cashier'],
            ],
        ])->assertRedirect(route('employees.index'));

        $this->assertDatabaseHas('branch_user', [
            'user_id' => $employee->id,
            'branch_id' => $branch->id,
            'role' => 'cashier',
        ]);
    }
}
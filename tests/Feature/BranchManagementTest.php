<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_users_cannot_manage_branches(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('branches.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_create_and_deactivate_a_branch(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_super_admin' => true])->save();

        $this->actingAs($admin)->post(route('branches.store'), [
            'code' => 'CB-001',
            'name' => 'Cabang Pusat',
            'address' => 'Jl. Merdeka 1',
            'phone' => '021123456',
        ])->assertRedirect(route('branches.index'));

        $branch = Branch::query()->firstOrFail();
        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'code' => 'CB-001',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->patch(route('branches.status', $branch))
            ->assertRedirect(route('branches.index'));

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'is_active' => false,
        ]);
    }
}
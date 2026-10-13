<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_dashboard_only_summarizes_assigned_branches(): void
    {
        $manager = User::factory()->create();
        $assignedBranch = Branch::create(['code' => 'CB-001', 'name' => 'Cabang Pusat']);
        $otherBranch = Branch::create(['code' => 'CB-002', 'name' => 'Cabang Lain']);
        $manager->branches()->attach($assignedBranch, ['role' => 'manager']);

        $this->createSale($manager, $assignedBranch, 'TRX-DASH-001', '50000.00');
        $this->createSale($manager, $otherBranch, 'TRX-DASH-002', '90000.00');

        $this->actingAs($manager)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('summary', function (array $summary) {
                $this->assertSame(1, $summary['transaction_count']);
                $this->assertSame(50000.0, $summary['revenue']);
                $this->assertSame(1, $summary['branch_count']);

                return true;
            })
            ->assertSee(now()->locale('id')->translatedFormat('l, d F Y'))
            ->assertViewHas('salesTrend', function ($salesTrend) {
                $this->assertCount(7, $salesTrend);
                $this->assertSame(50000.0, $salesTrend->last()['revenue']);

                return true;
            })
            ->assertSee('Cabang Pusat')
            ->assertDontSee('Cabang Lain');
    }

    private function createSale(User $user, Branch $branch, string $invoice, string $total): void
    {
        Sale::create([
            'invoice_no' => $invoice,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'subtotal' => $total,
            'grand_total' => $total,
            'paid_amount' => $total,
            'change_amount' => '0.00',
            'payment_method' => 'cash',
            'created_at' => now(),
        ]);
    }
}
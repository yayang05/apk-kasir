<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashSession;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashSessionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_open_and_close_a_session_with_cash_difference(): void
    {
        $user = User::factory()->create();
        $branch = Branch::create(['code' => 'CB-001', 'name' => 'Cabang Pusat']);
        $user->branches()->attach($branch, ['role' => 'cashier']);

        $this->actingAs($user)->get(route('cash-sessions.index'))->assertOk();
        $this->post(route('cash-sessions.store'), [
            'branch_id' => $branch->id,
            'opening_cash' => '10000',
        ])->assertRedirect(route('cash-sessions.index'));

        $session = CashSession::query()->firstOrFail();
        Sale::create([
            'invoice_no' => 'TRX-TEST-001',
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'cash_session_id' => $session->id,
            'subtotal' => '7500.00',
            'grand_total' => '7500.00',
            'paid_amount' => '10000.00',
            'change_amount' => '2500.00',
            'payment_method' => 'cash',
        ]);
        SalePayment::create([
            'sale_id' => Sale::query()->where('invoice_no', 'TRX-TEST-001')->value('id'),
            'user_id' => $user->id,
            'cash_session_id' => $session->id,
            'payment_method' => 'cash',
            'amount' => '7500.00',
            'amount_tendered' => '10000.00',
            'change_amount' => '2500.00',
            'paid_at' => now(),
        ]);

        $this->patch(route('cash-sessions.close', $session), [
            'closing_cash' => '16800',
        ])->assertRedirect(route('cash-sessions.index'));

        $this->assertSame('17500.00', $session->fresh()->expected_cash);
        $this->assertSame('16800.00', $session->fresh()->closing_cash);
        $this->assertSame('-700.00', $session->fresh()->difference);
        $this->get(route('cash-sessions.index'))->assertOk();
    }

    public function test_user_cannot_open_a_second_session_before_closing_the_first(): void
    {
        $user = User::factory()->create();
        $firstBranch = Branch::create(['code' => 'CB-001', 'name' => 'Cabang Satu']);
        $secondBranch = Branch::create(['code' => 'CB-002', 'name' => 'Cabang Dua']);
        $user->branches()->attach([$firstBranch->id => ['role' => 'cashier'], $secondBranch->id => ['role' => 'cashier']]);
        CashSession::create([
            'branch_id' => $firstBranch->id,
            'user_id' => $user->id,
            'opening_cash' => 0,
            'opened_at' => now(),
        ]);

        $this->actingAs($user)->post(route('cash-sessions.store'), [
            'branch_id' => $secondBranch->id,
            'opening_cash' => 0,
        ])->assertSessionHasErrors('branch_id');

        $this->assertSame(1, CashSession::query()->count());
    }

    public function test_user_cannot_open_a_session_at_an_unassigned_branch(): void
    {
        $user = User::factory()->create();
        $branch = Branch::create(['code' => 'CB-001', 'name' => 'Cabang Lain']);

        $this->actingAs($user)->post(route('cash-sessions.store'), [
            'branch_id' => $branch->id,
            'opening_cash' => 0,
        ])->assertForbidden();

        $this->assertSame(0, CashSession::query()->count());
    }
}
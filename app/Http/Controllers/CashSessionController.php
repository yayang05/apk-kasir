<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashSession;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CashSessionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $branches = $this->availableBranches($user);
        $openSession = CashSession::query()
            ->with('branch')
            ->where('user_id', $user->id)
            ->whereNull('closed_at')
            ->first();
          $sessions = CashSession::query()
            ->with('branch')
            ->where('user_id', $user->id)
            ->latest('opened_at')
            ->paginate(10);
        $openCashTotal = $openSession?->payments()->where('payment_method', 'cash')->sum('amount') ?? 0;

        return view('cash-sessions.index', compact('branches', 'openSession', 'openCashTotal', 'sessions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'opening_cash' => ['required', 'numeric', 'min:0'],
        ]);
        $branch = $this->availableBranches($request->user())
            ->firstWhere('id', (int) $validated['branch_id']);

        abort_unless($branch, 403);

        $session = DB::transaction(function () use ($request, $validated, $branch) {
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();

            if (CashSession::query()->where('user_id', $request->user()->id)->whereNull('closed_at')->exists()) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Tutup shift yang sedang berjalan sebelum membuka shift baru.',
                ]);
            }

            return CashSession::create([
                'branch_id' => $branch->id,
                'user_id' => $request->user()->id,
                'opening_cash' => $validated['opening_cash'],
                'opened_at' => now(),
            ]);
        }, 3);

        return redirect()->route('cash-sessions.index')->with('status', 'Shift kas berhasil dibuka.');
    }

    public function close(Request $request, CashSession $cashSession): RedirectResponse
    {
        abort_unless(
            $cashSession->user_id === $request->user()->id || $request->user()->isSuperAdmin(),
            403
        );

        $validated = $request->validate([
            'closing_cash' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($cashSession, $validated) {
            $session = CashSession::query()->whereKey($cashSession->id)->lockForUpdate()->firstOrFail();

            if ($session->closed_at) {
                throw ValidationException::withMessages([
                    'closing_cash' => 'Shift ini sudah ditutup.',
                ]);
            }

            $salesCents = SalePayment::query()
                ->where('cash_session_id', $session->id)
                ->where('payment_method', 'cash')
                ->sum(DB::raw('ROUND(amount * 100)'));
            $openingCents = $this->toMinorUnits($session->opening_cash);
            $expectedCents = $openingCents + (int) $salesCents;
            $closingCents = $this->toMinorUnits($validated['closing_cash']);

            $session->update([
                'expected_cash' => number_format($expectedCents / 100, 2, '.', ''),
                'closing_cash' => number_format($closingCents / 100, 2, '.', ''),
                'difference' => number_format(($closingCents - $expectedCents) / 100, 2, '.', ''),
                'closed_at' => now(),
            ]);
        }, 3);

        return redirect()->route('cash-sessions.index')->with('status', 'Shift kas berhasil ditutup.');
    }

    private function availableBranches(User $user): Collection
    {
        if ($user->isSuperAdmin()) {
            return Branch::query()->where('is_active', true)->orderBy('name')->get();
        }

        return $user->branches()
            ->where('branches.is_active', true)
            ->orderBy('branches.name')
            ->get();
    }

    private function toMinorUnits(string|int|float $amount): int
    {
        return (int) str_replace('.', '', number_format((float) $amount, 2, '.', ''));
    }
}
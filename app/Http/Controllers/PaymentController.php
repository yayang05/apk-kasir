<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashSession;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function store(Request $request, Sale $sale): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method' => ['required', Rule::in(['cash', 'transfer'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['required_if:payment_method,transfer', 'nullable', 'string', 'max:100'],
        ]);

        $branches = $request->user()->isSuperAdmin()
            ? Branch::query()->get()
            : $request->user()->branches()->get();

        abort_unless($branches->contains('id', $sale->branch_id), 403);

        DB::transaction(function () use ($request, $sale, $validated) {
            $lockedSale = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $paidCents = SalePayment::query()
                ->where('sale_id', $lockedSale->id)
                ->sum(DB::raw('ROUND(amount * 100)'));
            $totalCents = $this->toMinorUnits($lockedSale->grand_total);
            $balanceCents = max(0, $totalCents - (int) $paidCents);
            $amountCents = $this->toMinorUnits($validated['amount']);

            if ($balanceCents === 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Transaksi ini sudah lunas.',
                ]);
            }

            if ($amountCents > $balanceCents) {
                throw ValidationException::withMessages([
                    'amount' => 'Jumlah pembayaran melebihi sisa piutang.',
                ]);
            }

            $cashSession = null;

            if ($validated['payment_method'] === 'cash') {
                $cashSession = CashSession::query()
                    ->where('user_id', $request->user()->id)
                    ->where('branch_id', $lockedSale->branch_id)
                    ->whereNull('closed_at')
                    ->lockForUpdate()
                    ->first();

                if (! $cashSession) {
                    throw ValidationException::withMessages([
                        'payment_method' => 'Buka shift kas cabang ini sebelum menerima pelunasan tunai.',
                    ]);
                }
            }

            SalePayment::create([
                'sale_id' => $lockedSale->id,
                'user_id' => $request->user()->id,
                'cash_session_id' => $cashSession?->id,
                'payment_method' => $validated['payment_method'],
                'amount' => number_format($amountCents / 100, 2, '.', ''),
                'amount_tendered' => number_format($amountCents / 100, 2, '.', ''),
                'change_amount' => '0.00',
                'reference' => $validated['reference'] ?? null,
                'paid_at' => now(),
            ]);

            $newBalanceCents = $balanceCents - $amountCents;
            $lockedSale->update([
                'payment_status' => $newBalanceCents === 0 ? 'paid' : 'partial',
            ]);
        }, 3);

        return redirect()->route('sales.show', $sale)->with('status', 'Pembayaran berhasil dicatat.');
    }

    private function toMinorUnits(string|int|float $amount): int
    {
        return (int) str_replace('.', '', number_format((float) $amount, 2, '.', ''));
    }
}
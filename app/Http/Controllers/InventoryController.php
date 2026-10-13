<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $branches = $this->manageableBranches($request->user());
        abort_unless($request->user()->isSuperAdmin() || $branches->isNotEmpty(), 403);
        $branchId = $request->integer('branch_id') ?: $branches->first()?->id;

        if ($branchId && ! $branches->contains('id', (int) $branchId)) {
            abort(403);
        }

        $stocks = BranchStock::query()
            ->with(['product.category', 'branch'])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->string('search')->trim().'%';
                $query->whereHas('product', function ($query) use ($search) {
                    $query->where('name', 'like', $search)
                        ->orWhere('sku', 'like', $search)
                        ->orWhere('barcode', 'like', $search);
                });
            })
            ->orderBy('product_id')
            ->paginate(20)
            ->withQueryString();
        $recentMovements = StockMovement::query()
            ->with(['product', 'user', 'branch'])
            ->whereIn('branch_id', $branches->modelKeys())
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->latest()
            ->limit(12)
            ->get();

        return view('inventory.index', compact('branches', 'branchId', 'recentMovements', 'stocks'));
    }

    public function edit(Request $request, BranchStock $branchStock): View
    {
        abort_unless($this->manageableBranches($request->user())->contains('id', $branchStock->branch_id), 403);
        $branchStock->load(['branch', 'product']);

        return view('inventory.edit', compact('branchStock'));
    }

    public function reorderReport(Request $request): View
    {
        $branches = $this->manageableBranches($request->user());
        abort_unless($request->user()->isSuperAdmin() || $branches->isNotEmpty(), 403);
        $branchId = $request->integer('branch_id') ?: $branches->first()?->id;
        abort_unless($branchId && $branches->contains('id', (int) $branchId), 403);

        $items = BranchStock::query()
            ->with(['product.category', 'branch'])
            ->where('branch_id', $branchId)
            ->whereColumn('quantity', '<', 'products.min_stock')
            ->join('products', 'products.id', '=', 'branch_stocks.product_id')
            ->select('branch_stocks.*')
            ->orderBy('quantity')
            ->get();

        return view('inventory.reorder-report', compact('branchId', 'branches', 'items'));
    }

    public function update(Request $request, BranchStock $branchStock): RedirectResponse
    {
        abort_unless($this->manageableBranches($request->user())->contains('id', $branchStock->branch_id), 403);

        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'decimal:0,3', 'min:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($request, $branchStock, $validated) {
            $stock = BranchStock::query()->whereKey($branchStock->id)->lockForUpdate()->firstOrFail();
            $before = number_format((float) $stock->quantity, 3, '.', '');
            $after = number_format((float) $validated['quantity'], 3, '.', '');
            $change = number_format((float) $after - (float) $before, 3, '.', '');

            $stock->update(['quantity' => $after]);

            StockMovement::create([
                'branch_id' => $stock->branch_id,
                'product_id' => $stock->product_id,
                'user_id' => $request->user()->id,
                'movement_type' => 'adjustment',
                'quantity_change' => $change,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'reason' => $validated['reason'],
            ]);
        });

        return redirect()->route('inventory.index', ['branch_id' => $branchStock->branch_id])
            ->with('status', 'Stok opname tersimpan.');
    }

    private function manageableBranches(User $user)
    {
        if ($user->isSuperAdmin()) {
            return Branch::query()->where('is_active', true)->orderBy('name')->get();
        }

        return $user->branches()
            ->where('branches.is_active', true)
            ->wherePivot('role', 'manager')
            ->orderBy('branches.name')
            ->get();
    }
}

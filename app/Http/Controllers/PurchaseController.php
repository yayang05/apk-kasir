<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function index(Request $request): View
    {
        $branches = $this->manageableBranches($request->user(), activeOnly: false);
        abort_unless($request->user()->isSuperAdmin() || $branches->isNotEmpty(), 403);
        $branchId = $request->integer('branch_id') ?: null;

        if ($branchId && ! $branches->contains('id', (int) $branchId)) {
            abort(403);
        }

        $purchases = Purchase::query()
            ->with(['branch', 'supplier', 'user'])
            ->whereIn('branch_id', $branches->modelKeys())
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->latest('received_at')
            ->paginate(20)
            ->withQueryString();

        return view('purchases.index', compact('branches', 'branchId', 'purchases'));
    }

    public function create(Request $request): View
    {
        $branches = $this->manageableBranches($request->user());
        $branchId = $request->integer('branch_id') ?: $branches->first()?->id;

        if ($branchId && ! $branches->contains('id', (int) $branchId)) {
            abort(403);
        }

        return view('purchases.create', [
            'branches' => $branches,
            'branchId' => $branchId,
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $branch = $this->manageableBranches($request->user())
            ->firstWhere('id', (int) $validated['branch_id']);

        abort_unless($branch, 403);

        $purchase = DB::transaction(function () use ($request, $validated, $branch) {
            $supplier = Supplier::query()
                ->whereKey($validated['supplier_id'])
                ->where('is_active', true)
                ->first();

            if (! $supplier) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'Pemasok tidak aktif atau tidak ditemukan.',
                ]);
            }

            $items = collect($validated['items'])->keyBy('product_id');
            $stocks = BranchStock::query()
                ->with('product')
                ->where('branch_id', $branch->id)
                ->whereIn('product_id', $items->keys())
                ->orderBy('product_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            if ($stocks->count() !== $items->count()) {
                throw ValidationException::withMessages([
                    'items' => 'Satu atau lebih produk tidak tersedia untuk cabang ini.',
                ]);
            }

            $purchaseItems = [];
            $totalCents = 0;

            foreach ($items as $productId => $item) {
                $stock = $stocks->get($productId);
                $product = $stock->product;

                if (! $product->is_active) {
                    throw ValidationException::withMessages([
                        'items' => "Produk {$product->name} sudah tidak aktif.",
                    ]);
                }

                $quantity = number_format((float) $item['quantity'], 3, '.', '');
                $quantityMilli = (int) str_replace('.', '', $quantity);
                $unitCostCents = $this->toMinorUnits($item['unit_cost']);
                $lineTotalCents = (int) round($unitCostCents * $quantityMilli / 1000);
                $totalCents += $lineTotalCents;
                $purchaseItems[] = [
                    'stock' => $stock,
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_cost' => number_format($unitCostCents / 100, 2, '.', ''),
                    'line_total' => number_format($lineTotalCents / 100, 2, '.', ''),
                ];
            }

            $purchaseNo = 'PB-'.now()->format('YmdHis').'-'.Str::upper(Str::random(5));
            $purchase = Purchase::create([
                'purchase_no' => $purchaseNo,
                'supplier_id' => $supplier->id,
                'branch_id' => $branch->id,
                'user_id' => $request->user()->id,
                'supplier_invoice_no' => $validated['supplier_invoice_no'] ?? null,
                'total' => number_format($totalCents / 100, 2, '.', ''),
                'received_at' => now(),
            ]);

            foreach ($purchaseItems as $purchaseItem) {
                $stock = $purchaseItem['stock'];
                $product = $purchaseItem['product'];
                $before = number_format((float) $stock->quantity, 3, '.', '');
                $after = number_format((float) $before + (float) $purchaseItem['quantity'], 3, '.', '');

                $purchase->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'unit' => $product->unit,
                    'quantity' => $purchaseItem['quantity'],
                    'unit_cost' => $purchaseItem['unit_cost'],
                    'line_total' => $purchaseItem['line_total'],
                ]);

                $stock->update(['quantity' => $after]);
                StockMovement::create([
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                    'user_id' => $request->user()->id,
                    'movement_type' => 'purchase',
                    'quantity_change' => $purchaseItem['quantity'],
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                    'reason' => "Penerimaan {$purchaseNo}",
                ]);
            }

            return $purchase;
        }, 3);

        return redirect()->route('purchases.show', $purchase);
    }

    public function show(Request $request, Purchase $purchase): View
    {
        abort_unless(
            $this->manageableBranches($request->user(), activeOnly: false)->contains('id', $purchase->branch_id),
            403
        );

        return view('purchases.show', [
            'purchase' => $purchase->load(['branch', 'supplier', 'user', 'items']),
        ]);
    }

    public function destroy(Request $request, Purchase $purchase): RedirectResponse
    {
        abort_unless(
            $this->manageableBranches($request->user(), activeOnly: false)->contains('id', $purchase->branch_id),
            403
        );

        DB::transaction(function () use ($request, $purchase) {
            $items = $purchase->items()->get();
            $stocks = BranchStock::query()
                ->where('branch_id', $purchase->branch_id)
                ->whereIn('product_id', $items->pluck('product_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            foreach ($items as $item) {
                $stock = $stocks->get($item->product_id);
                $currentQuantity = (float) ($stock?->quantity ?? 0);

                if (! $stock || $currentQuantity < (float) $item->quantity) {
                    throw ValidationException::withMessages([
                        'purchase' => "Pembelian {$purchase->purchase_no} tidak dapat dihapus karena sebagian stoknya sudah terpakai. Gunakan koreksi stok/retur terlebih dahulu.",
                    ]);
                }
            }

            foreach ($items as $item) {
                $stock = $stocks->get($item->product_id);
                $before = number_format((float) $stock->quantity, 3, '.', '');
                $after = number_format((float) $stock->quantity - (float) $item->quantity, 3, '.', '');

                $stock->update(['quantity' => $after]);
                StockMovement::create([
                    'branch_id' => $purchase->branch_id,
                    'product_id' => $item->product_id,
                    'user_id' => $request->user()->id,
                    'movement_type' => 'purchase_reversal',
                    'quantity_change' => number_format(-(float) $item->quantity, 3, '.', ''),
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                    'reason' => "Pembatalan {$purchase->purchase_no}",
                ]);
            }

            $purchase->delete();
        }, 3);

        return redirect()->route('purchases.index', ['branch_id' => $purchase->branch_id])
            ->with('status', "Pembelian {$purchase->purchase_no} berhasil dihapus dan stok telah dikembalikan.");
    }

    private function manageableBranches(User $user, bool $activeOnly = true): Collection
    {
        if ($user->isSuperAdmin()) {
            return Branch::query()
                ->when($activeOnly, fn ($query) => $query->where('is_active', true))
                ->orderBy('name')
                ->get();
        }

        return $user->branches()
            ->when($activeOnly, fn ($query) => $query->where('branches.is_active', true))
            ->wherePivot('role', 'manager')
            ->orderBy('branches.name')
            ->get();
    }

    private function toMinorUnits(string|int|float $amount): int
    {
        return (int) str_replace('.', '', number_format((float) $amount, 2, '.', ''));
    }
}

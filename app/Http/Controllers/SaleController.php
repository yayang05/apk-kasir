<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function index(Request $request): View
    {
        $branches = $this->accessibleBranches($request->user(), activeOnly: false);
        $branchId = $request->integer('branch_id') ?: $branches->first()?->id;

        if ($branchId && ! $branches->contains('id', (int) $branchId)) {
            abort(403);
        }

        $sales = Sale::query()
            ->with(['branch', 'user', 'payments'])
            ->whereIn('branch_id', $branches->modelKeys())
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('created_at', $request->input('date')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('sales.index', compact('branches', 'branchId', 'sales'));
    }

    public function create(Request $request): View
    {
        $branches = $this->accessibleBranches($request->user());
        $branchId = $request->integer('branch_id') ?: $branches->first()?->id;

        if ($branchId && ! $branches->contains('id', (int) $branchId)) {
            abort(403);
        }

        $cashSession = $branchId
            ? CashSession::query()
                ->where('user_id', $request->user()->id)
                ->where('branch_id', $branchId)
                ->whereNull('closed_at')
                ->first()
            : null;

        $search = $request->string('search')->trim()->toString();
        $stocks = BranchStock::query()
            ->with('product.category')
            ->where('branch_id', $branchId)
            ->where('quantity', '>', 0)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('product', function ($query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('sku', 'like', '%'.$search.'%')
                        ->orWhere('barcode', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('product_id')
            ->paginate(24)
            ->withQueryString();

        $customers = Customer::query()->where('is_active', true)->orderBy('name')->get(['id', 'member_no', 'name', 'phone']);
        return view('sales.create', compact('branches', 'branchId', 'cashSession', 'customers', 'search', 'stocks'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'payment_method' => ['required', 'in:cash,transfer,credit'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'payment_reference' => ['required_if:payment_method,transfer', 'nullable', 'string', 'max:100'],
            'customer_name' => ['required_if:payment_method,credit', 'nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'order_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0'],
        ]);

        $branch = $this->accessibleBranches($request->user())
            ->firstWhere('id', (int) $validated['branch_id']);

        abort_unless($branch, 403);

        $sale = DB::transaction(function () use ($request, $validated, $branch) {
            $cashSession = CashSession::query()
                ->where('user_id', $request->user()->id)
                ->where('branch_id', $branch->id)
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->first();

            if (! $cashSession) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Buka shift kas untuk cabang ini sebelum memproses transaksi.',
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
                    'items' => 'Satu atau lebih produk tidak tersedia di cabang ini.',
                ]);
            }

            $lineItems = [];
            $subtotalCents = 0;

            foreach ($items as $productId => $item) {
                $stock = $stocks->get($productId);
                $product = $stock->product;
                $quantity = number_format((float) $item['quantity'], 3, '.', '');

                if (! $product->is_active) {
                    throw ValidationException::withMessages([
                        'items' => "Produk {$product->name} sudah tidak aktif.",
                    ]);
                }

                if ((float) $stock->quantity < (float) $quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Stok {$product->name} tidak mencukupi.",
                    ]);
                }

                $unitPriceCents = (int) str_replace('.', '', number_format((float) $product->sale_price, 2, '.', ''));
                $quantityMilli = (int) str_replace('.', '', $quantity);
                $lineTotalCents = (int) round($unitPriceCents * $quantityMilli / 1000);
                $subtotalCents += $lineTotalCents;
                $lineItems[] = [
                    'stock' => $stock,
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $product->sale_price,
                    'unit_cost' => $product->cost_price,
                    'line_total' => number_format($lineTotalCents / 100, 2, '.', ''),
                ];
            }

            $paidCents = (int) str_replace('.', '', number_format((float) $validated['paid_amount'], 2, '.', ''));

            $paymentMethod = $validated['payment_method'];

            if ($paymentMethod === 'cash' && $paidCents < $subtotalCents) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'Uang yang dibayarkan kurang dari total belanja.',
                ]);
            }

            if ($paymentMethod === 'transfer' && $paidCents !== $subtotalCents) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'Jumlah transfer harus sama dengan total belanja.',
                ]);
            }

            if ($paymentMethod === 'credit' && $paidCents > $subtotalCents) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'Uang muka kredit tidak boleh melebihi total belanja.',
                ]);
            }

            $amountAppliedCents = $paymentMethod === 'credit' ? $paidCents : $subtotalCents;
            $changeCents = $paymentMethod === 'cash' ? $paidCents - $subtotalCents : 0;
            $paymentStatus = $amountAppliedCents === 0
                ? 'unpaid'
                : ($amountAppliedCents < $subtotalCents ? 'partial' : 'paid');

            $invoiceNo = 'TRX-'.now()->format('YmdHis').'-'.Str::upper(Str::random(5));
            $sale = Sale::create([
                'invoice_no' => $invoiceNo,
                'branch_id' => $branch->id,
                'user_id' => $request->user()->id,
                'customer_id' => $validated['customer_id'] ?? null,
                'cash_session_id' => $cashSession->id,
                'subtotal' => number_format($subtotalCents / 100, 2, '.', ''),
                'grand_total' => number_format($subtotalCents / 100, 2, '.', ''),
                'paid_amount' => number_format($paidCents / 100, 2, '.', ''),
                'change_amount' => number_format($changeCents / 100, 2, '.', ''),
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'customer_name' => $validated['customer_name'] ?? null,
                'customer_phone' => $validated['customer_phone'] ?? null,
                'order_name' => $validated['order_name'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($amountAppliedCents > 0) {
                SalePayment::create([
                    'sale_id' => $sale->id,
                    'user_id' => $request->user()->id,
                    'cash_session_id' => in_array($paymentMethod, ['cash', 'credit'], true) ? $cashSession->id : null,
                    'payment_method' => $paymentMethod === 'credit' ? 'cash' : $paymentMethod,
                    'amount' => number_format($amountAppliedCents / 100, 2, '.', ''),
                    'amount_tendered' => number_format($paidCents / 100, 2, '.', ''),
                    'change_amount' => number_format($changeCents / 100, 2, '.', ''),
                    'reference' => $validated['payment_reference'] ?? null,
                    'paid_at' => now(),
                ]);
            }

            foreach ($lineItems as $lineItem) {
                $stock = $lineItem['stock'];
                $product = $lineItem['product'];
                $before = number_format((float) $stock->quantity, 3, '.', '');
                $after = number_format((float) $before - (float) $lineItem['quantity'], 3, '.', '');

                $sale->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'unit' => $product->unit,
                    'quantity' => $lineItem['quantity'],
                    'unit_price' => $lineItem['unit_price'],
                    'unit_cost' => $lineItem['unit_cost'],
                    'line_total' => $lineItem['line_total'],
                ]);

                $stock->update(['quantity' => $after]);
                StockMovement::create([
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                    'user_id' => $request->user()->id,
                    'movement_type' => 'sale',
                    'quantity_change' => number_format(-(float) $lineItem['quantity'], 3, '.', ''),
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                    'reason' => "Penjualan {$invoiceNo}",
                ]);
            }

            return $sale;
        }, 3);

        return redirect()->route('sales.show', $sale);
    }

    public function show(Request $request, Sale $sale): View
    {
        abort_unless(
            $this->accessibleBranches($request->user(), activeOnly: false)->contains('id', $sale->branch_id),
            403
        );

        return view('sales.show', [
            'sale' => $sale->load(['branch', 'user', 'customer', 'items', 'payments.user', 'returns.items', 'returns.user']),
            'openCashSession' => CashSession::query()
                ->where('user_id', $request->user()->id)
                ->where('branch_id', $sale->branch_id)
                ->whereNull('closed_at')
                ->first(),
        ]);
    }

    public function returnSale(Request $request, Sale $sale): RedirectResponse
    {
        $branch = $this->accessibleBranches($request->user(), activeOnly: false)
            ->firstWhere('id', $sale->branch_id);
        abort_unless($branch, 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'refund_method' => ['required', 'in:cash,transfer,credit_adjustment'],
            'refund_reference' => ['required_if:refund_method,transfer', 'nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['nullable', 'numeric', 'decimal:0,3', 'min:0'],
        ]);

        DB::transaction(function () use ($request, $sale, $validated, $branch) {
            $lockedSale = Sale::query()->with('items')->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $returnedQuantities = DB::table('sale_return_items')
                ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
                ->where('sale_returns.sale_id', $lockedSale->id)
                ->groupBy('sale_item_id')
                ->select('sale_item_id')
                ->selectRaw('SUM(sale_return_items.quantity) as returned_quantity')
                ->pluck('returned_quantity', 'sale_item_id');
            $selected = collect($validated['items'])->filter(fn ($quantity) => (float) $quantity > 0);
            $lines = [];
            $totalCents = 0;

            foreach ($selected as $saleItemId => $quantity) {
                $item = $lockedSale->items->firstWhere('id', (int) $saleItemId);
                if (! $item) {
                    throw ValidationException::withMessages(['items' => 'Item retur tidak valid.']);
                }

                $available = (float) $item->quantity - (float) ($returnedQuantities->get($item->id) ?? 0);
                if ((float) $quantity > $available) {
                    throw ValidationException::withMessages(['items' => "Jumlah retur {$item->product_name} melebihi sisa barang yang dapat diretur."]);
                }

                $lineCents = (int) round((float) $quantity * (float) $item->unit_price * 100);
                $totalCents += $lineCents;
                $lines[] = [$item, number_format((float) $quantity, 3, '.', ''), number_format($lineCents / 100, 2, '.', '')];
            }

            if ($lines === []) {
                throw ValidationException::withMessages(['items' => 'Pilih minimal satu barang untuk diretur.']);
            }

            $paidCents = (int) round($lockedSale->paid_total * 100);
            if ($validated['refund_method'] !== 'credit_adjustment' && $totalCents > $paidCents) {
                throw ValidationException::withMessages(['refund_method' => 'Nilai refund tunai/transfer tidak boleh melebihi pembayaran yang sudah diterima.']);
            }

            $return = SaleReturn::create([
                'sale_id' => $lockedSale->id,
                'branch_id' => $branch->id,
                'user_id' => $request->user()->id,
                'return_no' => 'RET-'.now()->format('YmdHis').'-'.Str::upper(Str::random(5)),
                'reason' => $validated['reason'],
                'total' => number_format($totalCents / 100, 2, '.', ''),
                'refund_method' => $validated['refund_method'],
                'refund_reference' => $validated['refund_reference'] ?? null,
                'returned_at' => now(),
            ]);

            foreach ($lines as [$item, $quantity, $lineTotal]) {
                $return->items()->create([
                    'sale_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'sku' => $item->sku,
                    'quantity' => $quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $lineTotal,
                ]);

                $stock = BranchStock::query()->where('branch_id', $branch->id)->where('product_id', $item->product_id)->lockForUpdate()->firstOrFail();
                $before = number_format((float) $stock->quantity, 3, '.', '');
                $after = number_format((float) $before + (float) $quantity, 3, '.', '');
                $stock->update(['quantity' => $after]);
                StockMovement::create([
                    'branch_id' => $branch->id,
                    'product_id' => $item->product_id,
                    'user_id' => $request->user()->id,
                    'movement_type' => 'return',
                    'quantity_change' => $quantity,
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                    'reason' => "Retur {$return->return_no}",
                ]);
            }
        }, 3);

        return redirect()->route('sales.show', $sale)->with('status', 'Retur berhasil dicatat dan stok dikembalikan.');
    }

    private function accessibleBranches(User $user, bool $activeOnly = true): Collection
    {
        if ($user->isSuperAdmin()) {
            return Branch::query()
                ->when($activeOnly, fn ($query) => $query->where('is_active', true))
                ->orderBy('name')
                ->get();
        }

        return $user->branches()
            ->when($activeOnly, fn ($query) => $query->where('branches.is_active', true))
            ->orderBy('branches.name')
            ->get();
    }
}

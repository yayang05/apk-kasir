<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashSession;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function export(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $branches = $this->reportableBranches($request->user());
        abort_unless($request->user()->isSuperAdmin() || $branches->isNotEmpty(), 403);

        $dateFrom = $validated['date_from'] ?? now()->toDateString();
        $dateTo = $validated['date_to'] ?? $dateFrom;

        if ($dateTo < $dateFrom) {
            throw ValidationException::withMessages([
                'date_to' => 'Tanggal akhir harus sama atau setelah tanggal awal.',
            ]);
        }

        $branchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;

        if ($branchId && ! $branches->contains('id', $branchId)) {
            abort(403);
        }

        $branchIds = ($branchId ? $branches->where('id', $branchId) : $branches)->modelKeys();
        $period = [
            CarbonImmutable::parse($dateFrom)->startOfDay(),
            CarbonImmutable::parse($dateTo)->endOfDay(),
        ];
        $fileName = 'laporan-penjualan-'.$dateFrom.'-'.$dateTo.'.csv';

        return response()->streamDownload(function () use ($branchIds, $period) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Invoice', 'Tanggal', 'Cabang', 'Kasir', 'Metode', 'Status', 'Total', 'Dibayar', 'Sisa'], ';');

            Sale::query()
                ->with(['branch:id,name', 'user:id,name'])
                ->withSum('payments', 'amount')
                ->whereIn('branch_id', $branchIds)
                ->whereBetween('created_at', $period)
                ->orderBy('created_at')
                ->chunk(500, function ($sales) use ($output) {
                    foreach ($sales as $sale) {
                        $paid = (float) ($sale->payments_sum_amount ?? 0);
                        fputcsv($output, [
                            $sale->invoice_no,
                            $sale->created_at->format('Y-m-d H:i:s'),
                            $sale->branch->name,
                            $sale->user->name,
                            $sale->payment_method,
                            $sale->payment_status,
                            number_format((float) $sale->grand_total, 2, '.', ''),
                            number_format($paid, 2, '.', ''),
                            number_format(max(0, (float) $sale->grand_total - $paid), 2, '.', ''),
                        ], ';');
                    }
                });

            fclose($output);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $branches = $this->reportableBranches($request->user());
        abort_unless($request->user()->isSuperAdmin() || $branches->isNotEmpty(), 403);

        $dateFrom = $validated['date_from'] ?? now()->toDateString();
        $dateTo = $validated['date_to'] ?? $dateFrom;

        if ($dateTo < $dateFrom) {
            throw ValidationException::withMessages([
                'date_to' => 'Tanggal akhir harus sama atau setelah tanggal awal.',
            ]);
        }

        $branchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;

        if ($branchId && ! $branches->contains('id', $branchId)) {
            abort(403);
        }

        $selectedBranches = $branchId
            ? $branches->where('id', $branchId)->values()
            : $branches;
        $branchIds = $selectedBranches->modelKeys();
        $period = [
            CarbonImmutable::parse($dateFrom)->startOfDay(),
            CarbonImmutable::parse($dateTo)->endOfDay(),
        ];

        $salesQuery = Sale::query()
            ->whereIn('sales.branch_id', $branchIds)
            ->whereBetween('sales.created_at', $period);
        $salesSummary = (clone $salesQuery)
            ->selectRaw('COUNT(*) as transaction_count, COALESCE(SUM(grand_total), 0) as revenue')
            ->first();
        $itemSummary = $this->saleItemsQuery($branchIds, $period)
            ->selectRaw('COALESCE(SUM(items.quantity), 0) as units_sold')
            ->selectRaw('COALESCE(SUM(items.quantity * items.unit_cost), 0) as cost_of_goods_sold')
            ->first();

        $revenue = (float) $salesSummary->revenue;
        $costOfGoods = (float) $itemSummary->cost_of_goods_sold;
        $summary = [
            'transaction_count' => (int) $salesSummary->transaction_count,
            'revenue' => $revenue,
            'units_sold' => (float) $itemSummary->units_sold,
            'cost_of_goods_sold' => $costOfGoods,
            'gross_profit' => $revenue - $costOfGoods,
        ];

        $purchaseSummary = Purchase::query()
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('received_at', $period)
            ->selectRaw('COUNT(*) as purchase_count, COALESCE(SUM(total), 0) as total')
            ->first();
        $purchaseSummary = [
            'count' => (int) $purchaseSummary->purchase_count,
            'total' => (float) $purchaseSummary->total,
        ];

        $inventorySummary = DB::table('branch_stocks as stocks')
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->whereIn('stocks.branch_id', $branchIds)
            ->selectRaw('COUNT(*) as product_count, COALESCE(SUM(stocks.quantity), 0) as units, COALESCE(SUM(stocks.quantity * products.cost_price), 0) as cost_value')
            ->selectRaw('SUM(CASE WHEN stocks.quantity <= 5 THEN 1 ELSE 0 END) as low_stock_count')
            ->first();
        $inventorySummary = [
            'product_count' => (int) $inventorySummary->product_count,
            'units' => (float) $inventorySummary->units,
            'cost_value' => (float) $inventorySummary->cost_value,
            'low_stock_count' => (int) $inventorySummary->low_stock_count,
        ];

        $dailySales = (clone $salesQuery)
            ->selectRaw('DATE(created_at) as sale_date, COUNT(*) as transaction_count, COALESCE(SUM(grand_total), 0) as revenue')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('sale_date')
            ->get();

        $cashierSummaries = (clone $salesQuery)
            ->join('users', 'users.id', '=', 'sales.user_id')
            ->selectRaw('sales.user_id, users.name as cashier_name, COUNT(*) as transaction_count, COALESCE(SUM(sales.grand_total), 0) as revenue')
            ->groupBy('sales.user_id', 'users.name')
            ->orderByDesc('revenue')
            ->get();

        $paymentsByMethod = DB::table('sale_payments as payments')
            ->join('sales', 'sales.id', '=', 'payments.sale_id')
            ->whereIn('sales.branch_id', $branchIds)
            ->whereBetween('payments.paid_at', $period)
            ->selectRaw('payments.payment_method, COALESCE(SUM(payments.amount), 0) as amount')
            ->groupBy('payments.payment_method')
            ->get()
            ->keyBy('payment_method');
        $creditSalesQuery = Sale::query()
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('created_at', $period)
            ->where('payment_method', 'credit');
        $creditSalesTotal = (float) (clone $creditSalesQuery)->sum('grand_total');
        $paidBySale = DB::table('sale_payments')
            ->select('sale_id')
            ->selectRaw('SUM(amount) as paid_total')
            ->groupBy('sale_id');
        $creditBalanceSummary = DB::table('sales')
            ->leftJoinSub($paidBySale, 'payment_totals', 'payment_totals.sale_id', '=', 'sales.id')
            ->whereIn('sales.branch_id', $branchIds)
            ->whereBetween('sales.created_at', $period)
            ->where('sales.payment_method', 'credit')
            ->whereRaw('sales.grand_total > COALESCE(payment_totals.paid_total, 0)')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('COALESCE(SUM(sales.grand_total - COALESCE(payment_totals.paid_total, 0)), 0) as outstanding')
            ->first();
        $openCreditSales = (clone $creditSalesQuery)
            ->with(['branch', 'user', 'payments'])
            ->whereRaw('(SELECT COALESCE(SUM(amount), 0) FROM sale_payments WHERE sale_payments.sale_id = sales.id) < sales.grand_total')
            ->latest()
            ->limit(20)
            ->get();
        $paymentSummary = [
            'cash_collected' => (float) ($paymentsByMethod->get('cash')->amount ?? 0),
            'transfer_collected' => (float) ($paymentsByMethod->get('transfer')->amount ?? 0),
            'credit_sales' => $creditSalesTotal,
            'open_credit_count' => (int) $creditBalanceSummary->count,
            'credit_outstanding' => (float) $creditBalanceSummary->outstanding,
        ];

        $branchSales = (clone $salesQuery)
            ->selectRaw('branch_id, COUNT(*) as transaction_count, COALESCE(SUM(grand_total), 0) as revenue')
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');
        $branchCosts = $this->saleItemsQuery($branchIds, $period)
            ->selectRaw('sales.branch_id, COALESCE(SUM(items.quantity * items.unit_cost), 0) as cost_of_goods_sold')
            ->groupBy('sales.branch_id')
            ->get()
            ->keyBy('branch_id');

        $branchSummaries = $selectedBranches->map(function (Branch $branch) use ($branchSales, $branchCosts) {
            $sales = $branchSales->get($branch->id);
            $revenue = (float) ($sales->revenue ?? 0);
            $cost = (float) ($branchCosts->get($branch->id)->cost_of_goods_sold ?? 0);

            return (object) [
                'branch' => $branch,
                'transaction_count' => (int) ($sales->transaction_count ?? 0),
                'revenue' => $revenue,
                'cost_of_goods_sold' => $cost,
                'gross_profit' => $revenue - $cost,
            ];
        });

        $topProducts = $this->saleItemsQuery($branchIds, $period)
            ->select('items.product_id', 'items.product_name', 'items.sku')
            ->selectRaw('SUM(items.quantity) as units_sold, SUM(items.line_total) as revenue')
            ->groupBy('items.product_id', 'items.product_name', 'items.sku')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        $cashDifferences = CashSession::query()
            ->with(['branch', 'user'])
            ->whereIn('branch_id', $branchIds)
            ->whereNotNull('closed_at')
            ->whereBetween('closed_at', $period)
            ->orderByDesc('closed_at')
            ->limit(20)
            ->get();

        return view('reports.index', compact(
            'branches',
            'branchId',
            'branchSummaries',
            'cashDifferences',
            'cashierSummaries',
            'dateFrom',
            'dateTo',
            'dailySales',
            'openCreditSales',
            'paymentSummary',
            'purchaseSummary',
            'inventorySummary',
            'summary',
            'topProducts',
        ));
    }

    public function detail(Request $request, string $report): View
    {
        $allowed = ['penjualan', 'pendapatan', 'laba-rugi', 'neraca', 'kredit', 'stok'];
        abort_unless(in_array($report, $allowed, true), 404);

        [$branches, $branchId, $dateFrom, $dateTo, $branchIds, $period] = $this->reportFilters($request);
        $data = $this->detailData($report, $branchIds, $period);
        $titles = [
            'penjualan' => 'Laporan Penjualan',
            'pendapatan' => 'Laporan Pendapatan',
            'laba-rugi' => 'Laporan Laba Rugi',
            'neraca' => 'Neraca',
            'kredit' => 'Laporan Kredit / Piutang',
            'stok' => 'Laporan Stok',
        ];

        return view('reports.detail', compact('branches', 'branchId', 'dateFrom', 'dateTo', 'report', 'data', 'titles'));
    }

    public function exportDetail(Request $request, string $report): StreamedResponse
    {
        $allowed = ['penjualan', 'pendapatan', 'laba-rugi', 'neraca', 'kredit', 'stok'];
        abort_unless(in_array($report, $allowed, true), 404);

        [$branches, $branchId, $dateFrom, $dateTo, $branchIds, $period] = $this->reportFilters($request);
        $data = $this->detailData($report, $branchIds, $period);
        $titles = [
            'penjualan' => 'Laporan Penjualan', 'pendapatan' => 'Laporan Pendapatan',
            'laba-rugi' => 'Laporan Laba Rugi', 'neraca' => 'Neraca',
            'kredit' => 'Laporan Kredit / Piutang', 'stok' => 'Laporan Stok',
        ];
        $fileName = 'laporan-'.$report.'-'.$dateFrom.'-'.$dateTo.'.csv';

        return response()->streamDownload(function () use ($report, $data, $titles) {
            $output = fopen('php://output', 'w');
            fputcsv($output, [$titles[$report]], ';');
            foreach ($data['headers'] as $header) {
                fputcsv($output, $header, ';');
            }
            foreach ($data['rows'] as $row) {
                fputcsv($output, $row, ';');
            }
            fclose($output);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function reportFilters(Request $request): array
    {
        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $branches = $this->reportableBranches($request->user());
        abort_unless($request->user()->isSuperAdmin() || $branches->isNotEmpty(), 403);
        $dateFrom = $validated['date_from'] ?? now()->toDateString();
        $dateTo = $validated['date_to'] ?? $dateFrom;
        if ($dateTo < $dateFrom) {
            throw ValidationException::withMessages(['date_to' => 'Tanggal akhir harus sama atau setelah tanggal awal.']);
        }
        $branchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;
        if ($branchId && ! $branches->contains('id', $branchId)) {
            abort(403);
        }
        $selected = $branchId ? $branches->where('id', $branchId) : $branches;
        return [$branches, $branchId, $dateFrom, $dateTo, $selected->modelKeys(), [CarbonImmutable::parse($dateFrom)->startOfDay(), CarbonImmutable::parse($dateTo)->endOfDay()]];
    }

    private function detailData(string $report, array $branchIds, array $period): array
    {
        if ($report === 'penjualan') {
            $rows = Sale::query()->with(['branch:id,name', 'user:id,name'])->whereIn('branch_id', $branchIds)->whereBetween('created_at', $period)->withSum('payments', 'amount')->orderBy('created_at')->get()->map(fn ($sale) => [$sale->invoice_no, $sale->created_at->format('d/m/Y H:i'), $sale->branch->name, $sale->user->name, $sale->payment_method, (float) $sale->grand_total, (float) $sale->payments_sum_amount, $sale->balance_due])->all();
            return ['headers' => [['Invoice', 'Tanggal', 'Cabang', 'Kasir', 'Metode', 'Total', 'Dibayar', 'Sisa']], 'rows' => $rows];
        }
        if ($report === 'pendapatan') {
            $rows = DB::table('sale_payments as p')->join('sales as s', 's.id', '=', 'p.sale_id')->join('branches as b', 'b.id', '=', 's.branch_id')->whereIn('s.branch_id', $branchIds)->whereBetween('p.paid_at', $period)->selectRaw("DATE(p.paid_at) as paid_date, b.name as branch_name, p.payment_method, COUNT(*) as payment_count, SUM(p.amount) as amount")->groupByRaw('DATE(p.paid_at), b.id, b.name, p.payment_method')->orderBy('paid_date')->get()->map(fn ($row) => [$row->paid_date, $row->branch_name, $row->payment_method, $row->payment_count, (float) $row->amount])->all();
            return ['headers' => [['Tanggal', 'Cabang', 'Metode', 'Jumlah Pembayaran', 'Pendapatan']], 'rows' => $rows];
        }
        if ($report === 'laba-rugi') {
            $sales = Sale::query()->whereIn('branch_id', $branchIds)->whereBetween('created_at', $period)->sum('grand_total');
            $cogs = $this->saleItemsQuery($branchIds, $period)->sum(DB::raw('items.quantity * items.unit_cost'));
            $purchases = Purchase::query()->whereIn('branch_id', $branchIds)->whereBetween('received_at', $period)->sum('total');
            $rows = [['Penjualan bersih', (float) $sales], ['Harga pokok penjualan (HPP)', -(float) $cogs], ['Laba kotor', (float) $sales - (float) $cogs], ['Pembelian barang (informasi arus barang)', -(float) $purchases], ['Laba bersih operasional*', (float) $sales - (float) $cogs]];
            return ['headers' => [['Komponen', 'Nilai']], 'rows' => $rows];
        }
        if ($report === 'neraca') {
            $cash = DB::table('sale_payments as p')->join('sales as s', 's.id', '=', 'p.sale_id')->whereIn('s.branch_id', $branchIds)->whereBetween('p.paid_at', $period)->sum('p.amount');
            $receivable = Sale::query()->whereIn('branch_id', $branchIds)->whereRaw("payment_method = 'credit'")->sum(DB::raw('grand_total - paid_amount'));
            $inventory = DB::table('branch_stocks as bs')->join('products as p', 'p.id', '=', 'bs.product_id')->whereIn('bs.branch_id', $branchIds)->sum(DB::raw('bs.quantity * p.cost_price'));
            $totalAssets = (float) $cash + (float) $receivable + (float) $inventory;
            return ['headers' => [['Posisi Keuangan', 'Nilai']], 'rows' => [['Kas diterima periode', (float) $cash], ['Piutang kredit', (float) $receivable], ['Persediaan (harga pokok)', (float) $inventory], ['Total aset', $totalAssets], ['Kewajiban tercatat', 0], ['Ekuitas estimasi', $totalAssets]]];
        }
        if ($report === 'kredit') {
            $rows = Sale::query()->with(['branch:id,name', 'user:id,name'])->whereIn('branch_id', $branchIds)->where('payment_method', 'credit')->whereBetween('created_at', $period)->withSum('payments', 'amount')->orderBy('created_at')->get()->map(fn ($sale) => [$sale->invoice_no, $sale->created_at->format('d/m/Y'), $sale->customer_name ?: '-', $sale->branch->name, (float) $sale->grand_total, (float) $sale->payments_sum_amount, $sale->balance_due])->all();
            return ['headers' => [['Invoice', 'Tanggal', 'Pelanggan', 'Cabang', 'Total Kredit', 'Dibayar', 'Sisa Piutang']], 'rows' => $rows];
        }
        $rows = DB::table('branch_stocks as bs')->join('branches as b', 'b.id', '=', 'bs.branch_id')->join('products as p', 'p.id', '=', 'bs.product_id')->whereIn('bs.branch_id', $branchIds)->select('b.name as branch_name', 'p.sku', 'p.name as product_name', 'p.unit', 'bs.quantity', 'p.cost_price')->orderBy('b.name')->orderBy('p.name')->get()->map(fn ($row) => [$row->branch_name, $row->sku, $row->product_name, $row->unit, (float) $row->quantity, (float) $row->cost_price, (float) $row->quantity * (float) $row->cost_price])->all();
        return ['headers' => [['Cabang', 'SKU', 'Produk', 'Satuan', 'Jumlah', 'Harga Modal', 'Nilai Stok']], 'rows' => $rows];
    }

    private function saleItemsQuery(array $branchIds, array $period): Builder
    {
        return DB::table('sale_items as items')
            ->join('sales', 'sales.id', '=', 'items.sale_id')
            ->whereIn('sales.branch_id', $branchIds)
            ->whereBetween('sales.created_at', $period);
    }

    private function reportableBranches(User $user): Collection
    {
        if ($user->isSuperAdmin()) {
            return Branch::query()->orderBy('name')->get();
        }

        return $user->branches()
            ->wherePivot('role', 'manager')
            ->orderBy('branches.name')
            ->get();
    }
}

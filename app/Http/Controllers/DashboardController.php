<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\CashSession;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $branches = $this->visibleBranches($user);
        $branchIds = $branches->modelKeys();
        $todaySales = Sale::query()
            ->whereIn('branch_id', $branchIds)
            ->whereDate('created_at', today());
        $transactionCount = (clone $todaySales)->count();
        $revenue = (float) (clone $todaySales)->sum('grand_total');
        $summary = [
            'revenue' => $revenue,
            'transaction_count' => $transactionCount,
            'average_ticket' => $transactionCount > 0 ? $revenue / $transactionCount : 0,
            'branch_count' => $branches->count(),
        ];

        $salesByBranch = (clone $todaySales)
            ->selectRaw('branch_id, COUNT(*) as transaction_count, COALESCE(SUM(grand_total), 0) as revenue')
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');
        $branchSummaries = $branches->map(function (Branch $branch) use ($salesByBranch) {
            $sales = $salesByBranch->get($branch->id);

            return (object) [
                'branch' => $branch,
                'transaction_count' => (int) ($sales->transaction_count ?? 0),
                'revenue' => (float) ($sales->revenue ?? 0),
            ];
        });

        $trendStart = today()->subDays(6)->startOfDay();
        $trendRows = Sale::query()
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('created_at', [$trendStart, now()])
            ->selectRaw('DATE(created_at) as sales_date, COALESCE(SUM(grand_total), 0) as revenue')
            ->groupBy('sales_date')
            ->get()
            ->keyBy('sales_date');
        $salesTrend = collect(range(6, 0))->map(function (int $daysAgo) use ($trendRows) {
            $date = today()->subDays($daysAgo);
            $row = $trendRows->get($date->toDateString());

            return [
                'label' => $date->locale('id')->translatedFormat('D'),
                'date' => $date->locale('id')->translatedFormat('d M'),
                'revenue' => (float) ($row->revenue ?? 0),
            ];
        })->values();

        $lowStock = BranchStock::query()
            ->with(['branch', 'product'])
            ->whereIn('branch_id', $branchIds)
            ->where('quantity', '<=', 5)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->orderBy('quantity')
            ->limit(8)
            ->get();
        $recentSales = Sale::query()
            ->with(['branch', 'user'])
            ->whereIn('branch_id', $branchIds)
            ->latest()
            ->limit(8)
            ->get();
        $cashSession = CashSession::query()
            ->with('branch')
            ->where('user_id', $user->id)
            ->whereNull('closed_at')
            ->first();
        $canViewFinancials = $user->isSuperAdmin()
            || $user->branches()->wherePivot('role', 'manager')->exists();
        $greeting = match (true) {
            now()->hour < 11 => 'Selamat pagi',
            now()->hour < 15 => 'Selamat siang',
            now()->hour < 19 => 'Selamat sore',
            default => 'Selamat malam',
        };

        return view('dashboard', compact(
            'branchSummaries',
            'canViewFinancials',
            'cashSession',
            'greeting',
            'lowStock',
            'recentSales',
            'salesTrend',
            'summary',
        ));
    }

    private function visibleBranches(User $user): Collection
    {
        if ($user->isSuperAdmin()) {
            return Branch::query()->where('is_active', true)->orderBy('name')->get();
        }

        return $user->branches()
            ->where('branches.is_active', true)
            ->orderBy('branches.name')
            ->get();
    }
}
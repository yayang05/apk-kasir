<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm text-gray-500">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</p>
                <h2 class="mt-1 text-xl font-semibold text-gray-900">{{ $greeting }}, {{ Auth::user()->name }}</h2>
            </div>
            <div class="flex flex-wrap items-end gap-4">
                <div x-data="dashboardClock" class="border-l border-gray-200 pl-4" role="timer" aria-label="Jam lokal">
                    <p class="text-xs font-medium uppercase text-gray-500">Waktu Lokal</p>
                    <time class="mt-1 block font-mono text-2xl font-semibold tabular-nums text-gray-900" x-text="time">{{ now()->format('H:i:s') }}</time>
                </div>
                <a href="{{ route('sales.create') }}" class="inline-flex items-center gap-2 rounded-md bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-700">
                    <span aria-hidden="true">+</span> Transaksi Baru
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-7">
        <div class="mx-auto max-w-7xl space-y-8 sm:px-6 lg:px-8">
            <section aria-label="Ringkasan hari ini" class="grid grid-cols-2 gap-x-6 gap-y-5 border-y border-gray-200 py-5 sm:grid-cols-4">
                <div><p class="text-sm text-gray-500">Omzet Hari Ini</p><p class="mt-1 text-2xl font-semibold text-gray-900">Rp{{ number_format($summary['revenue'], 0, ',', '.') }}</p></div>
                <div><p class="text-sm text-gray-500">Transaksi</p><p class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($summary['transaction_count']) }}</p></div>
                <div><p class="text-sm text-gray-500">Rata-rata Belanja</p><p class="mt-1 text-2xl font-semibold text-gray-900">Rp{{ number_format($summary['average_ticket'], 0, ',', '.') }}</p></div>
                <div><p class="text-sm text-gray-500">Cabang Aktif</p><p class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($summary['branch_count']) }}</p></div>
            </section>

            <section class="bg-white p-5 shadow-sm sm:rounded-lg sm:p-6">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h3 class="text-base font-semibold text-gray-900">Tren Omzet</h3>
                    <p class="text-sm text-gray-500">7 hari terakhir · cabang yang dapat diakses</p>
                </div>
                <div class="mt-5 h-64 sm:h-72" x-data="salesTrendChart(@js($salesTrend))">
                    <canvas x-ref="canvas" role="img" aria-label="Grafik tren omzet tujuh hari terakhir"></canvas>
                </div>
            </section>

            <section class="grid gap-8 lg:grid-cols-[minmax(0,1.35fr)_minmax(18rem,0.65fr)]">
                <div>
                    <div class="mb-3 flex items-center justify-between gap-4">
                        <h3 class="text-base font-semibold text-gray-900">Performa Cabang Hari Ini</h3>
                        @if ($canViewFinancials)
                            <a href="{{ route('reports.index') }}" class="text-sm font-medium text-indigo-700 hover:text-indigo-900">Laporan</a>
                        @endif
                    </div>
                    <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50"><tr>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Cabang</th>
                                <th class="px-5 py-3 text-right text-xs font-medium uppercase text-gray-500">Transaksi</th>
                                <th class="px-5 py-3 text-right text-xs font-medium uppercase text-gray-500">Omzet</th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse ($branchSummaries as $branchSummary)
                                    <tr>
                                        <td class="px-5 py-3 text-sm font-medium text-gray-900">{{ $branchSummary->branch->name }}<span class="ml-2 text-xs font-normal text-gray-500">{{ $branchSummary->branch->code }}</span></td>
                                        <td class="px-5 py-3 text-right text-sm text-gray-700">{{ number_format($branchSummary->transaction_count) }}</td>
                                        <td class="px-5 py-3 text-right text-sm font-medium text-gray-900">Rp{{ number_format($branchSummary->revenue, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="px-5 py-8 text-center text-sm text-gray-500">Belum ada cabang aktif yang tersedia.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <div class="mb-3 flex items-center justify-between gap-4">
                        <h3 class="text-base font-semibold text-gray-900">Shift Kas</h3>
                        <a href="{{ route('cash-sessions.index') }}" class="text-sm font-medium text-indigo-700 hover:text-indigo-900">Kelola</a>
                    </div>
                    <div class="bg-white p-5 shadow-sm sm:rounded-lg">
                        @if ($cashSession)
                            <div class="flex items-start gap-3">
                                <span class="mt-1 inline-block size-2.5 rounded-full bg-green-600" aria-label="Shift aktif"></span>
                                <div>
                                    <p class="font-semibold text-gray-900">Shift sedang berjalan</p>
                                    <p class="mt-1 text-sm text-gray-500">{{ $cashSession->branch->name }} · dibuka {{ $cashSession->opened_at->format('H:i') }}</p>
                                    <a href="{{ route('sales.create', ['branch_id' => $cashSession->branch_id]) }}" class="mt-4 inline-flex text-sm font-medium text-indigo-700 hover:text-indigo-900">Lanjutkan transaksi</a>
                                </div>
                            </div>
                        @else
                            <p class="font-semibold text-gray-900">Belum ada shift aktif</p>
                            <p class="mt-1 text-sm text-gray-500">Buka shift sebelum memulai transaksi.</p>
                            <a href="{{ route('cash-sessions.index') }}" class="mt-4 inline-flex text-sm font-medium text-indigo-700 hover:text-indigo-900">Buka shift kas</a>
                        @endif
                    </div>
                </div>
            </section>

            <section class="grid gap-8 lg:grid-cols-2">
                <div>
                    <div class="mb-3 flex items-center justify-between gap-4">
                        <h3 class="text-base font-semibold text-gray-900">Stok Menipis</h3>
                        @if (Auth::user()->isSuperAdmin() || Auth::user()->branches()->wherePivot('role', 'manager')->exists())
                            <a href="{{ route('inventory.index') }}" class="text-sm font-medium text-indigo-700 hover:text-indigo-900">Persediaan</a>
                        @endif
                    </div>
                    <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50"><tr>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Produk / Cabang</th>
                                <th class="px-5 py-3 text-right text-xs font-medium uppercase text-gray-500">Sisa</th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse ($lowStock as $stock)
                                    <tr>
                                        <td class="px-5 py-3 text-sm text-gray-900">{{ $stock->product->name }}<br><span class="text-xs text-gray-500">{{ $stock->branch->name }} · {{ $stock->product->sku }}</span></td>
                                        <td class="px-5 py-3 text-right text-sm font-semibold {{ (float) $stock->quantity <= 2 ? 'text-red-700' : 'text-amber-700' }}">{{ number_format((float) $stock->quantity, 3, ',', '.') }} {{ $stock->product->unit }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="px-5 py-8 text-center text-sm text-gray-500">Tidak ada stok menipis.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <div class="mb-3 flex items-center justify-between gap-4">
                        <h3 class="text-base font-semibold text-gray-900">Transaksi Terbaru</h3>
                        <a href="{{ route('sales.index') }}" class="text-sm font-medium text-indigo-700 hover:text-indigo-900">Semua transaksi</a>
                    </div>
                    <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50"><tr>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Invoice / Cabang</th>
                                <th class="px-5 py-3 text-right text-xs font-medium uppercase text-gray-500">Total</th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse ($recentSales as $sale)
                                    <tr>
                                        <td class="px-5 py-3 text-sm text-gray-900"><a href="{{ route('sales.show', $sale) }}" class="font-medium text-indigo-700 hover:text-indigo-900">{{ $sale->invoice_no }}</a><br><span class="text-xs text-gray-500">{{ $sale->branch->name }} · {{ $sale->created_at->format('d/m H:i') }}</span></td>
                                        <td class="px-5 py-3 text-right text-sm font-medium text-gray-900">Rp{{ number_format((float) $sale->grand_total, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="px-5 py-8 text-center text-sm text-gray-500">Belum ada transaksi.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>

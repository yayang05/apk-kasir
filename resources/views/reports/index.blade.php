<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight print:hidden">Laporan Operasional</h2>
    </x-slot>

    <div class="py-8">
        <div class="report-print mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-5 hidden items-start justify-between border-b border-gray-300 pb-4 print:flex">
                <div><h1 class="text-2xl font-bold text-gray-900">Laporan Operasional</h1><p class="mt-1 text-sm text-gray-600">Periode {{ \Carbon\CarbonImmutable::parse($dateFrom)->format('d/m/Y') }} s/d {{ \Carbon\CarbonImmutable::parse($dateTo)->format('d/m/Y') }}</p></div>
                <div class="text-right text-sm text-gray-600"><p>Dicetak {{ now()->format('d/m/Y H:i') }}</p><p>{{ config('app.name', 'Apk Kasir') }}</p></div>
            </div>
            <div class="mb-6 flex items-center justify-between print:hidden"><p class="text-sm text-gray-600">Gunakan filter untuk menyesuaikan laporan, lalu cetak atau simpan sebagai PDF.</p><button type="button" onclick="window.print()" class="inline-flex items-center rounded-md bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-700">Cetak Laporan</button></div>
            <form method="GET" action="{{ route('reports.index') }}" class="mb-6 flex flex-wrap items-end gap-3 print:hidden">
                <div>
                    <x-input-label for="date_from" value="Dari Tanggal" />
                    <x-text-input id="date_from" name="date_from" type="date" class="mt-1 block w-full" :value="$dateFrom" required />
                </div>
                <div>
                    <x-input-label for="date_to" value="Sampai Tanggal" />
                    <x-text-input id="date_to" name="date_to" type="date" class="mt-1 block w-full" :value="$dateTo" required />
                </div>
                <div>
                    <x-input-label for="branch_id" value="Cabang" />
                    <select id="branch_id" name="branch_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="">Semua cabang</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((int) $branchId === $branch->id)>{{ $branch->code }} · {{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-primary-button>Tampilkan Laporan</x-primary-button>
                <a href="{{ route('reports.export', ['date_from' => $dateFrom, 'date_to' => $dateTo, 'branch_id' => $branchId]) }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">Unduh CSV</a>
            </form>

            <section class="mb-7 grid grid-cols-1 gap-4 border-y border-gray-200 py-4 sm:grid-cols-3 print:grid-cols-3">
                <div><p class="text-sm text-gray-500">Nilai Modal Persediaan</p><p class="mt-1 text-lg font-semibold text-gray-900">Rp{{ number_format($inventorySummary['cost_value'], 0, ',', '.') }}</p></div>
                <div><p class="text-sm text-gray-500">Total Unit Tersedia</p><p class="mt-1 text-lg font-semibold text-gray-900">{{ number_format($inventorySummary['units'], 3, ',', '.') }}</p><p class="text-xs text-gray-500">{{ number_format($inventorySummary['product_count']) }} baris stok</p></div>
                <div><p class="text-sm text-gray-500">Stok Menipis</p><p class="mt-1 text-lg font-semibold {{ $inventorySummary['low_stock_count'] > 0 ? 'text-red-700' : 'text-green-800' }}">{{ number_format($inventorySummary['low_stock_count']) }} produk</p><p class="text-xs text-gray-500">batas peringatan: ≤ 5 unit</p></div>
            </section>

            <section class="grid grid-cols-2 gap-x-6 gap-y-5 border-y border-gray-200 py-5 sm:grid-cols-3 lg:grid-cols-6">
                <div><p class="text-sm text-gray-500">Transaksi</p><p class="mt-1 text-xl font-semibold text-gray-900">{{ number_format($summary['transaction_count']) }}</p></div>
                <div><p class="text-sm text-gray-500">Omzet</p><p class="mt-1 text-xl font-semibold text-gray-900">Rp{{ number_format($summary['revenue'], 0, ',', '.') }}</p></div>
                <div><p class="text-sm text-gray-500">Unit Terjual</p><p class="mt-1 text-xl font-semibold text-gray-900">{{ number_format($summary['units_sold'], 3, ',', '.') }}</p></div>
                <div><p class="text-sm text-gray-500">HPP</p><p class="mt-1 text-xl font-semibold text-gray-900">Rp{{ number_format($summary['cost_of_goods_sold'], 0, ',', '.') }}</p></div>
                <div><p class="text-sm text-gray-500">Laba Kotor</p><p class="mt-1 text-xl font-semibold {{ $summary['gross_profit'] < 0 ? 'text-red-700' : 'text-green-800' }}">Rp{{ number_format($summary['gross_profit'], 0, ',', '.') }}</p></div>
                <div><p class="text-sm text-gray-500">Pembelian Barang</p><p class="mt-1 text-xl font-semibold text-gray-900">Rp{{ number_format($purchaseSummary['total'], 0, ',', '.') }}</p><p class="text-xs text-gray-500">{{ number_format($purchaseSummary['count']) }} penerimaan</p></div>
            </section>

            <div class="mt-8 grid gap-9 lg:grid-cols-2">
                <section>
                    <h3 class="mb-3 text-base font-semibold text-gray-900">Tren Penjualan Harian</h3>
                    <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50"><tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Tanggal</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Transaksi</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Omzet</th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse ($dailySales as $dailySale)
                                    <tr><td class="px-4 py-3 text-sm text-gray-900">{{ \Carbon\CarbonImmutable::parse($dailySale->sale_date)->format('d/m/Y') }}</td><td class="px-4 py-3 text-right text-sm text-gray-700">{{ number_format($dailySale->transaction_count) }}</td><td class="px-4 py-3 text-right text-sm font-medium text-gray-900">Rp{{ number_format((float) $dailySale->revenue, 0, ',', '.') }}</td></tr>
                                @empty
                                    <tr><td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada penjualan pada periode ini.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section>
                    <h3 class="mb-3 text-base font-semibold text-gray-900">Performa Kasir</h3>
                    <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50"><tr><th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Kasir</th><th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Transaksi</th><th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Omzet</th></tr></thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse ($cashierSummaries as $cashier)
                                    <tr><td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $cashier->cashier_name }}</td><td class="px-4 py-3 text-right text-sm text-gray-700">{{ number_format($cashier->transaction_count) }}</td><td class="px-4 py-3 text-right text-sm font-medium text-gray-900">Rp{{ number_format((float) $cashier->revenue, 0, ',', '.') }}</td></tr>
                                @empty
                                    <tr><td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada data kasir.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <section class="mt-7">
                <h3 class="mb-3 text-base font-semibold text-gray-900">Pembayaran dan Piutang</h3>
                <div class="grid grid-cols-2 gap-x-6 gap-y-5 border-y border-gray-200 py-5 sm:grid-cols-3 lg:grid-cols-5">
                    <div><p class="text-sm text-gray-500">Tunai Diterima</p><p class="mt-1 text-lg font-semibold text-gray-900">Rp{{ number_format($paymentSummary['cash_collected'], 0, ',', '.') }}</p></div>
                    <div><p class="text-sm text-gray-500">Transfer Diterima</p><p class="mt-1 text-lg font-semibold text-gray-900">Rp{{ number_format($paymentSummary['transfer_collected'], 0, ',', '.') }}</p></div>
                    <div><p class="text-sm text-gray-500">Penjualan Kredit</p><p class="mt-1 text-lg font-semibold text-gray-900">Rp{{ number_format($paymentSummary['credit_sales'], 0, ',', '.') }}</p></div>
                    <div><p class="text-sm text-gray-500">Transaksi Belum Lunas</p><p class="mt-1 text-lg font-semibold text-amber-800">{{ number_format($paymentSummary['open_credit_count']) }}</p></div>
                    <div><p class="text-sm text-gray-500">Saldo Piutang</p><p class="mt-1 text-lg font-semibold text-amber-800">Rp{{ number_format($paymentSummary['credit_outstanding'], 0, ',', '.') }}</p></div>
                </div>
            </section>

            <div class="mt-8 grid gap-9 lg:grid-cols-2">
                <section>
                    <h3 class="mb-3 text-base font-semibold text-gray-900">Ringkasan per Cabang</h3>
                    <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50"><tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cabang</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Transaksi</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Omzet</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Laba Kotor</th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach ($branchSummaries as $branchSummary)
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $branchSummary->branch->name }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-700">{{ number_format($branchSummary->transaction_count) }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-700">Rp{{ number_format($branchSummary->revenue, 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-700">Rp{{ number_format($branchSummary->gross_profit, 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <section>
                    <h3 class="mb-3 text-base font-semibold text-gray-900">Produk Terlaris</h3>
                    <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50"><tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Unit</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Omzet</th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse ($topProducts as $product)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ $product->product_name }}<br><span class="text-xs text-gray-500">{{ $product->sku }}</span></td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-700">{{ number_format((float) $product->units_sold, 3, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-700">Rp{{ number_format((float) $product->revenue, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500">Tidak ada penjualan pada periode ini.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <section class="mt-9">
                <h3 class="mb-3 text-base font-semibold text-gray-900">Daftar Piutang Kredit</h3>
                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice / Pelanggan</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cabang / Kasir</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Dibayar</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Sisa</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($openCreditSales as $creditSale)
                                <tr>
                                    <td class="px-4 py-3 text-sm"><a href="{{ route('sales.show', $creditSale) }}" class="font-medium text-indigo-700 hover:text-indigo-900">{{ $creditSale->invoice_no }}</a><br><span class="text-xs text-gray-500">{{ $creditSale->customer_name ?: 'Pelanggan tidak dicatat' }} · {{ $creditSale->created_at->format('d/m/Y H:i') }}</span></td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $creditSale->branch->name }}<br><span class="text-xs text-gray-500">{{ $creditSale->user->name }}</span></td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-700">Rp{{ number_format((float) $creditSale->grand_total, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-700">Rp{{ number_format($creditSale->paid_total, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-sm font-semibold text-amber-800">Rp{{ number_format($creditSale->balance_due, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">Tidak ada piutang terbuka pada periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="mt-9">
                <h3 class="mb-3 text-base font-semibold text-gray-900">Selisih Tutup Kas</h3>
                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Waktu / Petugas</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cabang</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Harapan</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Fisik</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Selisih</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($cashDifferences as $session)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $session->closed_at->format('d/m/Y H:i') }}<br><span class="text-xs text-gray-500">{{ $session->user->name }}</span></td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $session->branch->name }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-700">Rp{{ number_format((float) $session->expected_cash, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-700">Rp{{ number_format((float) $session->closing_cash, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-sm font-medium {{ (float) $session->difference < 0 ? 'text-red-700' : 'text-gray-900' }}">Rp{{ number_format((float) $session->difference, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada shift ditutup pada periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>

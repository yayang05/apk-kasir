<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Persediaan Barang</h2>
            @if ($branchId)
                <a href="{{ route('purchases.create', ['branch_id' => $branchId]) }}" class="inline-flex items-center rounded-md bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-700">Terima Barang dari Pembelian</a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-auth-session-status class="mb-4" :status="session('status')" />

            <form method="GET" action="{{ route('inventory.index') }}" class="mb-4 flex flex-col gap-3 sm:flex-row">
                <select name="branch_id" required class="block w-full sm:max-w-xs border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                    <option value="">Pilih cabang</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) $branchId === $branch->id)>{{ $branch->code }} · {{ $branch->name }}</option>
                    @endforeach
                </select>
                <x-text-input name="search" type="search" class="block w-full sm:max-w-md" placeholder="Cari produk, SKU, atau barcode" :value="request('search')" />
                <x-primary-button>Tampilkan</x-primary-button>
            </form>

            @if ($branchId)
                <div class="mb-4 flex justify-end">
                    <a href="{{ route('inventory.reorder-report', ['branch_id' => $branchId]) }}" class="inline-flex items-center rounded-md border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100">Laporan Reorder / Stok Menipis</a>
                </div>
                <div class="mb-5 flex flex-wrap items-center gap-2 text-sm">
                    <a href="{{ route('purchases.index', ['branch_id' => $branchId]) }}" class="font-medium text-indigo-700 hover:text-indigo-900">Pembelian / barang masuk</a>
                    <span class="text-gray-400" aria-hidden="true">›</span>
                    <span class="font-semibold text-gray-900">Persediaan cabang</span>
                    <span class="text-gray-400" aria-hidden="true">›</span>
                    <a href="{{ route('sales.create', ['branch_id' => $branchId]) }}" class="font-medium text-indigo-700 hover:text-indigo-900">Kasir / barang keluar</a>
                </div>
            @endif

            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kategori</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Stok</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($stocks as $stock)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $stock->product->sku }}</td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $stock->product->name }} <span class="font-normal text-gray-500">/ {{ $stock->product->unit }}</span></td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $stock->product->category->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold text-gray-900">{{ number_format((float) $stock->quantity, 3, ',', '.') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm"><a href="{{ route('inventory.edit', $stock) }}" class="text-indigo-700 hover:text-indigo-900">Stok opname</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">Belum ada data stok untuk cabang ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $stocks->links() }}</div>

            <section class="mt-8">
                <h3 class="mb-3 text-base font-semibold text-gray-900">Perubahan Stok Terbaru</h3>
                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Waktu / Produk</th>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Jenis / Alasan</th>
                            <th class="px-5 py-3 text-right text-xs font-medium uppercase text-gray-500">Perubahan</th>
                            <th class="px-5 py-3 text-right text-xs font-medium uppercase text-gray-500">Stok Akhir</th>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Petugas</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($recentMovements as $movement)
                                <tr>
                                    <td class="px-5 py-3 text-sm text-gray-800">{{ $movement->created_at->format('d/m/Y H:i') }}<br><span class="text-xs text-gray-500">{{ $movement->product->name }} · {{ $movement->branch->name }}</span></td>
                                    <td class="px-5 py-3 text-sm text-gray-700">
                                        {{ ['purchase' => 'Barang masuk', 'sale' => 'Penjualan', 'adjustment' => 'Stok opname'][$movement->movement_type] ?? $movement->movement_type }}<br>
                                        <span class="text-xs text-gray-500">{{ $movement->reason }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-right text-sm font-medium {{ (float) $movement->quantity_change >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ (float) $movement->quantity_change > 0 ? '+' : '' }}{{ number_format((float) $movement->quantity_change, 3, ',', '.') }}</td>
                                    <td class="px-5 py-3 text-right text-sm text-gray-700">{{ number_format((float) $movement->quantity_after, 3, ',', '.') }} {{ $movement->product->unit }}</td>
                                    <td class="px-5 py-3 text-sm text-gray-700">{{ $movement->user->name ?? 'Sistem' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-gray-500">Belum ada pergerakan stok pada cabang ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>

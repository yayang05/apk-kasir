<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pembelian Barang</h2>
            @if (Auth::user()->isSuperAdmin() || Auth::user()->branches()->wherePivot('role', 'manager')->exists())
                <a href="{{ route('purchases.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">Terima Barang</a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <form method="GET" action="{{ route('purchases.index') }}" class="mb-4 flex flex-col gap-3 sm:flex-row">
                <select name="branch_id" class="block w-full sm:max-w-xs border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                    <option value="">Semua cabang yang ditugaskan</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) $branchId === $branch->id)>{{ $branch->code }} · {{ $branch->name }}</option>
                    @endforeach
                </select>
                <x-primary-button>Filter</x-primary-button>
                <a href="{{ route('purchases.index') }}" class="inline-flex items-center px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Reset</a>
            </form>

            <div class="mb-5 flex flex-wrap items-center gap-2 text-sm" aria-label="Alur barang">
                @if (Auth::user()->isSuperAdmin())
                    <a href="{{ route('suppliers.index') }}" class="font-medium text-indigo-700 hover:text-indigo-900">Pemasok</a>
                @else
                    <span class="text-gray-600">Pemasok</span>
                @endif
                <span class="text-gray-400" aria-hidden="true">›</span>
                <span class="font-semibold text-gray-900">Terima barang</span>
                <span class="text-gray-400" aria-hidden="true">›</span>
                <a href="{{ route('inventory.index', ['branch_id' => $branchId]) }}" class="font-medium text-indigo-700 hover:text-indigo-900">Persediaan bertambah</a>
            </div>

            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nomor Pembelian</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal / Cabang</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pemasok</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($purchases as $purchase)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $purchase->purchase_no }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $purchase->received_at->format('d/m/Y H:i') }}<br><span class="text-gray-500">{{ $purchase->branch->name }}</span></td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $purchase->supplier->name }}<br><span class="text-gray-500">{{ $purchase->user->name }}</span></td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold text-gray-900">Rp{{ number_format((float) $purchase->total, 0, ',', '.') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <div class="inline-flex items-center gap-3">
                                        <a href="{{ route('purchases.show', $purchase) }}" class="text-indigo-700 hover:text-indigo-900">Detail</a>
                                        <form method="POST" action="{{ route('purchases.destroy', $purchase) }}" onsubmit="return confirm('Hapus pembelian ini? Stok akan dikurangi sesuai isi pembelian.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-rose-700 hover:text-rose-900">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">Belum ada penerimaan barang.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $purchases->links() }}</div>
        </div>
    </div>
</x-app-layout>

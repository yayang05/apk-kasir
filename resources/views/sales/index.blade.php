<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Riwayat Transaksi</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <form method="GET" action="{{ route('sales.index') }}" class="mb-4 flex flex-col gap-3 sm:flex-row">
                <select name="branch_id" class="block w-full sm:max-w-xs border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                    <option value="">Semua cabang yang ditugaskan</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) $branchId === $branch->id)>{{ $branch->code }} · {{ $branch->name }}</option>
                    @endforeach
                </select>
                <x-text-input name="date" type="date" class="block w-full sm:max-w-xs" :value="request('date')" />
                <x-primary-button>Filter</x-primary-button>
                <a href="{{ route('sales.index') }}" class="inline-flex items-center px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Reset</a>
            </form>

            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Waktu</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cabang / Kasir</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pembayaran</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Sisa</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($sales as $sale)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $sale->invoice_no }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $sale->created_at->format('d/m/Y H:i') }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $sale->branch->name }}<br><span class="text-gray-500">{{ $sale->user->name }}</span></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    {{ ['cash' => 'Tunai', 'transfer' => 'Transfer', 'credit' => 'Kredit'][$sale->payment_method] ?? $sale->payment_method }}<br>
                                    <span class="text-xs {{ $sale->balance_due > 0 ? 'text-amber-800' : 'text-green-700' }}">{{ ['paid' => 'Lunas', 'partial' => 'Cicilan', 'unpaid' => 'Belum dibayar'][$sale->payment_status] ?? 'Lunas' }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold text-gray-900">Rp{{ number_format((float) $sale->grand_total, 0, ',', '.') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm {{ $sale->balance_due > 0 ? 'font-semibold text-amber-800' : 'text-gray-500' }}">Rp{{ number_format($sale->balance_due, 0, ',', '.') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm"><a href="{{ route('sales.show', $sale) }}" class="text-indigo-700 hover:text-indigo-900">Lihat struk</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500">Belum ada transaksi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $sales->links() }}</div>
        </div>
    </div>
</x-app-layout>
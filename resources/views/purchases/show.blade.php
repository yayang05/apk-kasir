<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Detail Penerimaan</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <dl class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt class="text-sm text-gray-500">Nomor Pembelian</dt><dd class="font-semibold text-gray-900">{{ $purchase->purchase_no }}</dd></div>
                    <div><dt class="text-sm text-gray-500">Cabang</dt><dd class="font-medium text-gray-900">{{ $purchase->branch->name }}</dd></div>
                    <div><dt class="text-sm text-gray-500">Pemasok</dt><dd class="font-medium text-gray-900">{{ $purchase->supplier->name }}</dd></div>
                    <div><dt class="text-sm text-gray-500">Faktur Pemasok</dt><dd class="font-medium text-gray-900">{{ $purchase->supplier_invoice_no ?: '-' }}</dd></div>
                    <div><dt class="text-sm text-gray-500">Diterima</dt><dd class="font-medium text-gray-900">{{ $purchase->received_at->format('d/m/Y H:i') }}</dd></div>
                    <div><dt class="text-sm text-gray-500">Petugas</dt><dd class="font-medium text-gray-900">{{ $purchase->user->name }}</dd></div>
                </dl>

                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Jumlah</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Harga Beli</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach ($purchase->items as $item)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $item->product_name }} <span class="text-gray-500">({{ $item->sku }})</span></td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-700">{{ number_format((float) $item->quantity, 3, ',', '.') }} {{ $item->unit }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-700">Rp{{ number_format((float) $item->unit_cost, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-700">Rp{{ number_format((float) $item->line_total, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot><tr><th colspan="3" class="px-4 py-4 text-right text-sm font-semibold text-gray-900">Total</th><td class="px-4 py-4 text-right text-sm font-bold text-gray-900">Rp{{ number_format((float) $purchase->total, 0, ',', '.') }}</td></tr></tfoot>
                    </table>
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-4 border-t border-gray-200 pt-4 text-sm">
                    <a href="{{ route('purchases.index', ['branch_id' => $purchase->branch_id]) }}" class="text-indigo-700 hover:text-indigo-900">Kembali ke pembelian</a>
                    <form method="POST" action="{{ route('purchases.destroy', $purchase) }}" onsubmit="return confirm('Hapus pembelian ini? Stok akan dikurangi sesuai isi pembelian.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="font-semibold text-rose-700 hover:text-rose-900">Hapus pembelian</button>
                    </form>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>

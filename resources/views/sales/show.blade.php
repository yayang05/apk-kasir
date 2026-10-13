<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4 print:hidden">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Struk Transaksi</h2>
            <button type="button" onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">Cetak</button>
        </div>
    </x-slot>

    <div class="py-8 print:py-0">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 print:max-w-none print:px-0">
            <x-auth-session-status class="mb-4 print:hidden" :status="session('status')" />
            @if ($errors->any())
                <div class="mb-4 border-s-4 border-red-600 bg-red-50 p-4 text-sm text-red-800 print:hidden" role="alert">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif
            <div class="receipt-print bg-white p-6 shadow-sm sm:rounded-lg print:shadow-none print:p-0">
                <div class="border-b border-gray-200 pb-4 text-center">
                    <h3 class="text-lg font-bold text-gray-900">{{ $sale->branch->name }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ $sale->branch->address }}</p>
                    <p class="mt-4 font-semibold text-gray-900">{{ $sale->invoice_no }}</p>
                    <p class="text-sm text-gray-500">{{ $sale->created_at->format('d/m/Y H:i:s') }} · {{ $sale->user->name }}</p>
                    <p class="mt-2 text-sm font-medium text-gray-700">Metode awal: {{ ['cash' => 'Tunai', 'transfer' => 'Transfer', 'credit' => 'Kredit'][$sale->payment_method] ?? $sale->payment_method }}</p>
                    @if ($sale->customer)
                        <p class="mt-2 text-sm text-gray-700">Member: {{ $sale->customer->name }} · {{ $sale->customer->member_no }}</p>
                    @endif
                    @if ($sale->order_name)
                        <p class="mt-1 text-sm text-gray-700">Order: {{ $sale->order_name }}</p>
                    @endif
                    @if ($sale->customer_name)
                        <p class="mt-2 text-sm text-gray-700">Pelanggan: {{ $sale->customer_name }}{{ $sale->customer_phone ? ' · '.$sale->customer_phone : '' }}</p>
                    @endif
                </div>
                @if ($sale->notes)
                    <p class="border-b border-gray-200 pb-3 text-sm text-gray-600">Catatan: {{ $sale->notes }}</p>
                @endif

                <div class="divide-y divide-gray-100 py-3">
                    @foreach ($sale->items as $item)
                        <div class="py-3">
                            <div class="flex justify-between gap-4 text-sm">
                                <span class="font-medium text-gray-900">{{ $item->product_name }}</span>
                                <span class="text-gray-700">Rp{{ number_format((float) $item->line_total, 0, ',', '.') }}</span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">{{ $item->sku }} · {{ number_format((float) $item->quantity, 3, ',', '.') }} {{ $item->unit }} × Rp{{ number_format((float) $item->unit_price, 0, ',', '.') }}</p>
                        </div>
                    @endforeach
                </div>

                <dl class="space-y-2 border-t border-gray-200 pt-4 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-600">Subtotal</dt><dd class="font-medium text-gray-900">Rp{{ number_format((float) $sale->subtotal, 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between text-base"><dt class="font-semibold text-gray-900">Total</dt><dd class="font-bold text-gray-900">Rp{{ number_format((float) $sale->grand_total, 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-600">Sudah Dibayar</dt><dd class="text-gray-900">Rp{{ number_format($sale->paid_total, 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-600">Sisa Piutang</dt><dd class="font-semibold {{ $sale->balance_due > 0 ? 'text-amber-800' : 'text-green-800' }}">Rp{{ number_format($sale->balance_due, 0, ',', '.') }}</dd></div>
                    @if ($sale->change_amount > 0)
                        <div class="flex justify-between"><dt class="text-gray-600">Kembalian Awal</dt><dd class="text-gray-900">Rp{{ number_format((float) $sale->change_amount, 0, ',', '.') }}</dd></div>
                    @endif
                </dl>

                <section class="mt-6 border-t border-gray-200 pt-4">
                    <h4 class="text-sm font-semibold text-gray-900">Riwayat Pembayaran</h4>
                    <div class="mt-3 divide-y divide-gray-100">
                        @forelse ($sale->payments as $payment)
                            <div class="flex flex-wrap justify-between gap-2 py-2 text-sm">
                                <span class="text-gray-600">{{ $payment->paid_at->format('d/m/Y H:i') }} · {{ $payment->payment_method === 'cash' ? 'Tunai' : 'Transfer' }}{{ $payment->reference ? ' · '.$payment->reference : '' }}</span>
                                <span class="font-medium text-gray-900">Rp{{ number_format((float) $payment->amount, 0, ',', '.') }}</span>
                            </div>
                        @empty
                            <p class="py-2 text-sm text-gray-500">Belum ada pembayaran.</p>
                        @endforelse
                    </div>
                </section>

                @if ($sale->balance_due > 0)
                    <section class="mt-6 border-t border-amber-200 pt-5 print:hidden" x-data="{ method: 'cash' }">
                        <h4 class="text-sm font-semibold text-gray-900">Catat Pelunasan</h4>
                        <p class="mt-1 text-sm text-gray-600">Sisa piutang Rp{{ number_format($sale->balance_due, 0, ',', '.') }}</p>
                        <form method="POST" action="{{ route('sales.payments.store', $sale) }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                            @csrf
                            <div>
                                <x-input-label for="repayment_method" value="Metode" />
                                <select id="repayment_method" name="payment_method" x-model="method" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="cash">Tunai</option>
                                    <option value="transfer">Transfer</option>
                                </select>
                            </div>
                            <div>
                                <x-input-label for="repayment_amount" value="Jumlah Pembayaran (Rp)" />
                                <x-text-input id="repayment_amount" name="amount" type="number" min="1" max="{{ $sale->balance_due }}" step="1" class="mt-1 block w-full" required />
                            </div>
                            <div x-show="method === 'transfer'" x-cloak class="sm:col-span-2">
                                <x-input-label for="repayment_reference" value="Nomor Referensi Transfer" />
                                <x-text-input id="repayment_reference" name="reference" type="text" maxlength="100" x-bind:required="method === 'transfer'" class="mt-1 block w-full" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-primary-button>Catat Pembayaran</x-primary-button>
                            </div>
                        </form>
                    </section>
                @endif

                @php
                    $returnedByItem = $sale->returns->flatMap->items->groupBy('sale_item_id')->map(fn ($items) => $items->sum(fn ($item) => (float) $item->quantity));
                    $returnableItems = $sale->items->filter(fn ($item) => (float) $item->quantity - (float) ($returnedByItem->get($item->id) ?? 0) > 0);
                @endphp
                @if ($returnableItems->isNotEmpty())
                    <section class="mt-6 border-t border-red-200 pt-5 print:hidden" x-data="{ method: 'cash' }">
                        <h4 class="text-sm font-semibold text-gray-900">Retur / Refund Barang</h4>
                        <p class="mt-1 text-sm text-gray-600">Pilih jumlah barang yang dikembalikan. Stok akan otomatis bertambah dan riwayat retur tercatat.</p>
                        <form method="POST" action="{{ route('sales.returns.store', $sale) }}" class="mt-4 space-y-4">
                            @csrf
                            <div class="overflow-x-auto rounded-md border border-gray-200">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left">Produk</th><th class="px-3 py-2 text-right">Sisa</th><th class="px-3 py-2 text-right">Jumlah Retur</th></tr></thead>
                                    <tbody class="divide-y divide-gray-200">
                                        @foreach ($returnableItems as $item)
                                            @php $remaining = (float) $item->quantity - (float) ($returnedByItem->get($item->id) ?? 0); @endphp
                                            <tr><td class="px-3 py-2">{{ $item->product_name }}<br><span class="text-xs text-gray-500">{{ $item->sku }}</span></td><td class="px-3 py-2 text-right">{{ number_format($remaining, 3, ',', '.') }} {{ $item->unit }}</td><td class="px-3 py-2 text-right"><input name="items[{{ $item->id }}]" type="number" min="0" max="{{ $remaining }}" step="0.001" class="w-28 rounded-md border-gray-300 text-right shadow-sm" value="0"></td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div><x-input-label for="return_reason" value="Alasan Retur" /><x-text-input id="return_reason" name="reason" type="text" class="mt-1 block w-full" maxlength="255" required placeholder="Contoh: barang rusak" /></div>
                                <div><x-input-label for="refund_method" value="Penyelesaian" /><select id="refund_method" name="refund_method" x-model="method" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><option value="cash">Refund Tunai</option><option value="transfer">Refund Transfer</option><option value="credit_adjustment">Potong Piutang</option></select></div>
                                <div x-show="method === 'transfer'" x-cloak><x-input-label for="refund_reference" value="Referensi Transfer" /><x-text-input id="refund_reference" name="refund_reference" type="text" maxlength="100" x-bind:required="method === 'transfer'" class="mt-1 block w-full" /></div>
                            </div>
                            <button type="submit" onclick="return confirm('Catat retur dan kembalikan stok?')" class="inline-flex items-center rounded-md bg-red-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-800">Simpan Retur</button>
                        </form>
                    </section>
                @endif

                @if ($sale->returns->isNotEmpty())
                    <section class="mt-6 border-t border-gray-200 pt-4 print:hidden">
                        <h4 class="text-sm font-semibold text-gray-900">Riwayat Retur</h4>
                        <div class="mt-3 divide-y divide-gray-100">
                            @foreach ($sale->returns as $return)
                                <div class="flex flex-wrap justify-between gap-2 py-2 text-sm"><span class="text-gray-600">{{ $return->return_no }} · {{ $return->returned_at->format('d/m/Y H:i') }} · {{ $return->reason }}</span><span class="font-medium text-red-700">-Rp{{ number_format((float) $return->total, 0, ',', '.') }}</span></div>
                            @endforeach
                        </div>
                    </section>
                @endif

                <p class="mt-6 border-t border-gray-200 pt-4 text-center text-sm text-gray-500">Terima kasih telah berbelanja.</p>
                <div class="mt-5 flex justify-center gap-4 text-sm print:hidden">
                    <a href="{{ route('sales.create', ['branch_id' => $sale->branch_id]) }}" class="text-indigo-700 hover:text-indigo-900">Transaksi Baru</a>
                    <a href="{{ route('sales.index', ['branch_id' => $sale->branch_id]) }}" class="text-gray-600 hover:text-gray-900">Riwayat Transaksi</a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

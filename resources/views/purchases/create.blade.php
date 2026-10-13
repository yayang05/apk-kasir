<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Terima Pembelian</h2></x-slot>
    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8" x-data="{
            catalog: @js($products->map(fn ($product) => ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'unit' => $product->unit, 'cost' => (float) $product->cost_price])->values()),
            selected: '',
            items: [],
            add() {
                const product = this.catalog.find((entry) => entry.id === Number(this.selected));
                if (!product) return;
                const existing = this.items.find((entry) => entry.product_id === product.id);
                if (existing) existing.quantity += 1;
                else this.items.push({ product_id: product.id, name: product.name, sku: product.sku, unit: product.unit, quantity: 1, unit_cost: product.cost });
                this.selected = '';
            },
            total() { return this.items.reduce((sum, item) => sum + Math.round(item.quantity * item.unit_cost * 100), 0); },
            money(cents) { return new Intl.NumberFormat('id-ID').format(Math.round(cents / 100)); }
        }">
            <div class="mb-5 flex flex-wrap items-center gap-2 text-sm" aria-label="Alur barang">
                @if (Auth::user()->isSuperAdmin())
                    <a href="{{ route('suppliers.index') }}" class="font-medium text-indigo-700 hover:text-indigo-900">Pemasok</a>
                @else
                    <span class="text-gray-600">Pemasok</span>
                @endif
                <span class="text-gray-400" aria-hidden="true">›</span>
                <span class="font-semibold text-gray-900">Terima barang</span>
                <span class="text-gray-400" aria-hidden="true">›</span>
                <a href="{{ route('inventory.index', ['branch_id' => $branchId]) }}" class="font-medium text-indigo-700 hover:text-indigo-900">Persediaan cabang</a>
            </div>

            @if ($errors->any())
                <div class="mb-5 border-s-4 border-red-600 bg-red-50 p-4 text-sm text-red-800" role="alert">
                    <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            @if ($branches->isEmpty() || $suppliers->isEmpty() || $products->isEmpty())
                <div class="mb-5 border-s-4 border-amber-500 bg-amber-50 p-4 text-sm text-amber-900">
                    Penerimaan barang memerlukan cabang aktif, pemasok aktif, dan produk aktif.
                </div>
            @endif

            <form method="POST" action="{{ route('purchases.store') }}" class="space-y-6">
                @csrf
                <section class="grid gap-5 bg-white p-6 shadow-sm sm:grid-cols-2 sm:rounded-lg">
                    <div>
                        <x-input-label for="branch_id" value="Cabang Penerima" />
                        <select id="branch_id" name="branch_id" required class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((int) old('branch_id', $branchId) === $branch->id)>{{ $branch->code }} · {{ $branch->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="supplier_id" value="Pemasok" />
                        <select id="supplier_id" name="supplier_id" required class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">Pilih pemasok</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->code }} · {{ $supplier->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('supplier_id')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="supplier_invoice_no" value="Nomor Faktur Pemasok (opsional)" />
                        <x-text-input id="supplier_invoice_no" name="supplier_invoice_no" type="text" class="mt-1 block w-full" :value="old('supplier_invoice_no')" maxlength="255" />
                        <x-input-error :messages="$errors->get('supplier_invoice_no')" class="mt-2" />
                    </div>
                </section>

                <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <div class="mb-4">
                        <h3 class="text-base font-semibold text-gray-900">Barang Diterima</h3>
                        <p class="mt-1 text-sm text-gray-500">Setelah disimpan, jumlah barang otomatis masuk ke stok cabang terpilih.</p>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <select x-model="selected" class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">Pilih produk untuk diterima</option>
                            <template x-for="product in catalog" :key="product.id">
                                <option :value="product.id" x-text="product.sku + ' · ' + product.name"></option>
                            </template>
                        </select>
                        <button type="button" @click="add()" :disabled="!selected" class="inline-flex shrink-0 items-center justify-center rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 disabled:cursor-not-allowed disabled:opacity-50">Tambah Produk</button>
                    </div>
                    <x-input-error :messages="$errors->get('items')" class="mt-2" />

                    <div class="mt-5 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Jumlah</th>
                                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Harga Beli</th>
                                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                                    <th class="px-3 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <template x-for="(item, index) in items" :key="item.product_id">
                                    <tr>
                                        <td class="px-3 py-3 text-sm font-medium text-gray-900"><span x-text="item.name"></span><input type="hidden" :name="`items[${index}][product_id]`" :value="item.product_id"></td>
                                        <td class="px-3 py-3"><input type="number" min="0.001" step="0.001" x-model.number="item.quantity" :name="`items[${index}][quantity]`" class="w-28 rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" aria-label="Jumlah"></td>
                                        <td class="px-3 py-3"><input type="number" min="0" step="0.01" x-model.number="item.unit_cost" :name="`items[${index}][unit_cost]`" class="w-32 rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" aria-label="Harga beli"></td>
                                        <td class="px-3 py-3 text-right text-sm text-gray-700" x-text="'Rp' + money(Math.round(item.quantity * item.unit_cost * 100))"></td>
                                        <td class="px-3 py-3 text-right"><button type="button" @click="items.splice(index, 1)" class="text-sm text-red-700 hover:text-red-900">Hapus</button></td>
                                    </tr>
                                </template>
                                <tr x-show="items.length === 0"><td colspan="5" class="px-3 py-8 text-center text-sm text-gray-500">Belum ada produk.</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-5 flex items-center justify-between border-t border-gray-200 pt-4">
                        <span class="font-semibold text-gray-900">Total Pembelian</span>
                        <span class="text-xl font-bold text-gray-900" x-text="'Rp' + money(total())"></span>
                    </div>
                </section>

                <div class="flex items-center gap-3">
                    <x-primary-button x-bind:disabled="items.length === 0">Simpan Barang Masuk</x-primary-button>
                    <a href="{{ route('purchases.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
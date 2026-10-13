<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Kasir</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8" x-data="{
            cart: [],
            paid: '',
            paymentMethod: 'cash',
            paymentReference: '',
            add(product) {
                const item = this.cart.find((entry) => entry.id === product.id);
                if (item) {
                    if (item.quantity + 1 <= item.stock) item.quantity += 1;
                    return;
                }
                this.cart.push({ ...product, quantity: 1 });
            },
            total() {
                return this.cart.reduce((sum, item) => sum + Math.round(item.price * item.quantity * 100), 0);
            },
            money(cents) {
                return new Intl.NumberFormat('id-ID').format(Math.round(cents / 100));
            },
            canCheckout() {
                const paidCents = Math.round(Number(this.paid || 0) * 100);
                if (this.cart.length === 0) return false;
                if (this.paymentMethod === 'cash') return paidCents >= this.total();
                if (this.paymentMethod === 'transfer') return paidCents === this.total() && this.paymentReference.trim() !== '';
                return paidCents <= this.total();
            }
        }" x-init="$nextTick(() => { if (window.matchMedia('(min-width: 768px)').matches) $refs.productSearch.focus() })">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-sm text-gray-500">Cabang aktif</p>
                    <p class="font-semibold text-gray-900">{{ $branches->firstWhere('id', $branchId)?->name ?? 'Belum ada cabang yang ditugaskan' }}</p>
                </div>
                <x-auth-session-status :status="session('status')" />
            </div>

            @if ($branchId && ! $cashSession)
                <div class="mb-5 border-s-4 border-amber-500 bg-amber-50 p-4 text-sm text-amber-900">
                    Buka shift kas untuk cabang ini sebelum memproses transaksi.
                    <a href="{{ route('cash-sessions.index') }}" class="ml-1 font-semibold underline">Buka shift kas</a>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-5 border-s-4 border-red-600 bg-red-50 p-4 text-sm text-red-800" role="alert">
                    <p class="font-semibold">Transaksi belum dapat disimpan.</p>
                    <ul class="mt-2 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <section>
                    <form method="GET" action="{{ route('sales.create') }}" class="mb-4 flex flex-col gap-3 sm:flex-row">
                        <select name="branch_id" required class="block w-full sm:max-w-xs border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((int) $branchId === $branch->id)>{{ $branch->code }} · {{ $branch->name }}</option>
                            @endforeach
                        </select>
                        <x-text-input x-ref="productSearch" name="search" type="search" class="block w-full" placeholder="Pindai barcode atau cari nama / SKU" :value="$search" />
                        <x-primary-button>Cari</x-primary-button>
                    </form>

                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div class="divide-y divide-gray-200">
                            @forelse ($stocks as $stock)
                                <div class="flex items-center justify-between gap-4 p-4">
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-gray-900">{{ $stock->product->name }}</p>
                                        <p class="text-sm text-gray-500">{{ $stock->product->sku }} · {{ $stock->product->category->name }}</p>
                                        <p class="text-xs text-gray-500">Stok {{ number_format((float) $stock->quantity, 3, ',', '.') }} {{ $stock->product->unit }}</p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <p class="mb-2 text-sm font-semibold text-gray-900">Rp{{ number_format((float) $stock->product->sale_price, 0, ',', '.') }}</p>
                                        <button type="button"
                                            @click="add({ id: {{ $stock->product->id }}, name: @js($stock->product->name), sku: @js($stock->product->sku), unit: @js($stock->product->unit), price: {{ (float) $stock->product->sale_price }}, stock: {{ (float) $stock->quantity }} })"
                                            class="text-sm font-medium text-indigo-700 hover:text-indigo-900">Tambah</button>
                                    </div>
                                </div>
                            @empty
                                <p class="px-6 py-12 text-center text-sm text-gray-500">Tidak ada produk dengan stok tersedia untuk pencarian ini.</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="mt-4">{{ $stocks->links() }}</div>
                </section>

                <aside class="h-fit bg-white p-5 shadow-sm sm:rounded-lg">
                    <h3 class="text-base font-semibold text-gray-900">Keranjang</h3>
                    <div class="mt-4 space-y-4">
                        <template x-for="item in cart" :key="item.id">
                            <div class="border-b border-gray-200 pb-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900" x-text="item.name"></p>
                                        <p class="text-xs text-gray-500" x-text="item.sku"></p>
                                    </div>
                                    <button type="button" @click="cart = cart.filter((entry) => entry.id !== item.id)" class="text-sm text-red-700 hover:text-red-900" :aria-label="'Hapus ' + item.name">Hapus</button>
                                </div>
                                <div class="mt-3 flex items-center justify-between gap-3">
                                    <input type="number" min="0.001" step="0.001" :max="item.stock" x-model.number="item.quantity" class="w-28 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :aria-label="'Jumlah ' + item.name">
                                    <span class="text-sm font-medium text-gray-900" x-text="'Rp' + money(Math.round(item.price * item.quantity * 100))"></span>
                                </div>
                                <input type="hidden" :name="`items[${cart.indexOf(item)}][product_id]`" :value="item.id" form="checkout-form">
                                <input type="hidden" :name="`items[${cart.indexOf(item)}][quantity]`" :value="item.quantity" form="checkout-form">
                            </div>
                        </template>
                    </div>

                    <form id="checkout-form" method="POST" action="{{ route('sales.store') }}" class="mt-4 space-y-4">
                        @csrf
                        <input type="hidden" name="branch_id" value="{{ $branchId }}">
                        <div class="flex items-center justify-between border-t border-gray-200 pt-4">
                            <span class="text-sm font-semibold text-gray-900">Total</span>
                            <span class="text-lg font-bold text-gray-900" x-text="'Rp' + money(total())"></span>
                        </div>
                        <div>
                            <x-input-label for="payment_method" value="Metode Pembayaran" />
                            <select id="payment_method" name="payment_method" x-model="paymentMethod" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                <option value="cash">Tunai</option>
                                <option value="transfer">Transfer</option>
                                <option value="credit">Kredit / Ngutang</option>
                            </select>
                        </div>
                        <div x-show="paymentMethod === 'credit'" x-cloak>
                            <x-input-label for="customer_name" value="Nama Pelanggan Kredit" />
                            <x-text-input id="customer_name" name="customer_name" type="text" :value="old('customer_name')" maxlength="255" x-bind:required="paymentMethod === 'credit'" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('customer_name')" class="mt-2" />
                        </div>
                        <div x-show="paymentMethod === 'credit'" x-cloak>
                            <x-input-label for="customer_phone" value="Telepon Pelanggan (opsional)" />
                            <x-text-input id="customer_phone" name="customer_phone" type="tel" :value="old('customer_phone')" maxlength="30" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('customer_phone')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="customer_id" value="Pelanggan / Member (opsional)" />
                            <select id="customer_id" name="customer_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><option value="">Pilih pelanggan</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((string) old('customer_id') === (string) $customer->id)>{{ $customer->name }} · {{ $customer->member_no }}</option>@endforeach</select>
                        </div>
                        <div><x-input-label for="order_name" value="Nama Order (opsional)" /><x-text-input id="order_name" name="order_name" type="text" maxlength="100" class="mt-1 block w-full" placeholder="Contoh: Pesanan Budi" /></div>
                        <div><x-input-label for="notes" value="Catatan Transaksi (opsional)" /><textarea id="notes" name="notes" maxlength="1000" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="Catatan order"></textarea></div>
                        <div>
                            <x-input-label for="paid_amount" value="Uang Diterima (Rp)" x-show="paymentMethod === 'cash'" />
                            <x-input-label for="paid_amount" value="Jumlah Transfer (Rp)" x-show="paymentMethod === 'transfer'" x-cloak />
                            <x-input-label for="paid_amount" value="Uang Muka (Rp), opsional" x-show="paymentMethod === 'credit'" x-cloak />
                            <x-text-input id="paid_amount" name="paid_amount" type="number" min="0" step="1" x-model.number="paid" class="mt-1 block w-full" :required="true" />
                            <x-input-error :messages="$errors->get('paid_amount')" class="mt-2" />
                        </div>
                        <div x-show="paymentMethod === 'transfer'" x-cloak>
                            <x-input-label for="payment_reference" value="Nomor Referensi Transfer" />
                            <x-text-input id="payment_reference" name="payment_reference" type="text" x-model="paymentReference" class="mt-1 block w-full" maxlength="100" x-bind:required="paymentMethod === 'transfer'" />
                            <x-input-error :messages="$errors->get('payment_reference')" class="mt-2" />
                        </div>
                        <div class="flex items-center justify-between text-sm" x-show="paymentMethod === 'cash'">
                            <span class="text-gray-600">Kembalian</span>
                            <span class="font-semibold text-gray-900" x-text="'Rp' + money(Math.max(0, Math.round((Number(paid || 0) * 100) - total())))"></span>
                        </div>
                        <div class="flex items-center justify-between text-sm" x-show="paymentMethod === 'credit'" x-cloak>
                            <span class="text-gray-600">Sisa Piutang</span>
                            <span class="font-semibold text-amber-800" x-text="'Rp' + money(Math.max(0, total() - Math.round(Number(paid || 0) * 100)))"></span>
                        </div>
                        <x-input-error :messages="$errors->get('items')" />
                        @if ($cashSession)
                            <x-primary-button class="w-full justify-center" x-bind:disabled="!canCheckout()">Simpan Transaksi</x-primary-button>
                        @else
                            <x-primary-button class="w-full justify-center" disabled>Buka Shift untuk Bertransaksi</x-primary-button>
                        @endif
                    </form>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>

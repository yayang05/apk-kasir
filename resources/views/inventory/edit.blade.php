<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Stok Opname</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <dl class="mb-6 grid gap-4 sm:grid-cols-2">
                    <div><dt class="text-sm text-gray-500">Cabang</dt><dd class="font-medium text-gray-900">{{ $branchStock->branch->name }}</dd></div>
                    <div><dt class="text-sm text-gray-500">Produk</dt><dd class="font-medium text-gray-900">{{ $branchStock->product->name }} ({{ $branchStock->product->sku }})</dd></div>
                    <div><dt class="text-sm text-gray-500">Stok Tercatat</dt><dd class="font-medium text-gray-900">{{ number_format((float) $branchStock->quantity, 3, ',', '.') }} {{ $branchStock->product->unit }}</dd></div>
                </dl>

                <form method="POST" action="{{ route('inventory.update', $branchStock) }}" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="quantity" value="Hasil Hitung Fisik" />
                        <x-text-input id="quantity" name="quantity" type="number" min="0" step="0.001" class="mt-1 block w-full" :value="old('quantity', $branchStock->quantity)" required />
                        <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="reason" value="Catatan Opname" />
                        <x-text-input id="reason" name="reason" type="text" class="mt-1 block w-full" :value="old('reason')" placeholder="Contoh: hitung stok rak" required maxlength="255" />
                        <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-3">
                        <x-primary-button>Simpan Hasil Opname</x-primary-button>
                        <a href="{{ route('inventory.index', ['branch_id' => $branchStock->branch_id]) }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
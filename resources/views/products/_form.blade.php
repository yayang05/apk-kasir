<form method="POST" action="{{ $action }}" class="space-y-5">
    @csrf
    @if ($editing)
        @method('PUT')
    @endif

    <div>
        <x-input-label for="category_id" value="Kategori" />
        <select id="category_id" name="category_id" required class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
            <option value="">Pilih kategori</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) old('category_id', $product?->category_id) === (string) $category->id)>{{ $category->name }}{{ $category->is_active ? '' : ' (Nonaktif)' }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <x-input-label for="sku" value="SKU" />
            <x-text-input id="sku" name="sku" type="text" class="mt-1 block w-full" :value="old('sku', $product?->sku)" required maxlength="50" />
            <x-input-error :messages="$errors->get('sku')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="barcode" value="Barcode" />
            <x-text-input id="barcode" name="barcode" type="text" class="mt-1 block w-full" :value="old('barcode', $product?->barcode)" maxlength="100" />
            <x-input-error :messages="$errors->get('barcode')" class="mt-2" />
        </div>
    </div>

    <div>
        <x-input-label for="name" value="Nama Produk" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $product?->name)" required maxlength="255" />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div class="grid gap-5 sm:grid-cols-5">
        <div>
            <x-input-label for="unit" value="Satuan" />
            <x-text-input id="unit" name="unit" type="text" class="mt-1 block w-full" :value="old('unit', $product?->unit ?? 'pcs')" required maxlength="30" />
            <x-input-error :messages="$errors->get('unit')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="cost_price" value="Harga Beli (Rp)" />
            <x-text-input id="cost_price" name="cost_price" type="number" min="0" step="0.01" class="mt-1 block w-full" :value="old('cost_price', $product?->cost_price ?? '0.00')" required />
            <x-input-error :messages="$errors->get('cost_price')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="sale_price" value="Harga Jual (Rp)" />
            <x-text-input id="sale_price" name="sale_price" type="number" min="0" step="0.01" class="mt-1 block w-full" :value="old('sale_price', $product?->sale_price)" required />
            <x-input-error :messages="$errors->get('sale_price')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="min_stock" value="Minimum Stok" />
            <x-text-input id="min_stock" name="min_stock" type="number" min="0" step="0.001" class="mt-1 block w-full" :value="old('min_stock', $product?->min_stock ?? '5.000')" required />
            <x-input-error :messages="$errors->get('min_stock')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="max_stock" value="Target Stok" />
            <x-text-input id="max_stock" name="max_stock" type="number" min="0" step="0.001" class="mt-1 block w-full" :value="old('max_stock', $product?->max_stock ?? '10.000')" required />
            <x-input-error :messages="$errors->get('max_stock')" class="mt-2" />
        </div>
    </div>

    <div class="flex items-center gap-3">
        <x-primary-button>{{ $editing ? 'Simpan Perubahan' : 'Simpan Produk' }}</x-primary-button>
        <a href="{{ route('products.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
    </div>
</form>

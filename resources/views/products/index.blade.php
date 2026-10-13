<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Produk</h2>
            <a href="{{ route('products.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">Tambah Produk</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-auth-session-status class="mb-4" :status="session('status')" />
            <form method="GET" action="{{ route('products.index') }}" class="mb-4 flex gap-2">
                <x-text-input name="search" type="search" class="block w-full max-w-md" placeholder="Cari nama, SKU, atau barcode" :value="request('search')" />
                <x-primary-button>Cari</x-primary-button>
                @if (request('search'))
                    <a href="{{ route('products.index') }}" class="inline-flex items-center px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Reset</a>
                @endif
            </form>

            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">SKU / Barcode</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kategori</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Harga Jual</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($products as $product)
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-500"><span class="font-medium text-gray-900">{{ $product->sku }}</span><br>{{ $product->barcode ?: 'Tanpa barcode' }}</td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $product->name }} <span class="font-normal text-gray-500">/ {{ $product->unit }}</span></td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $product->category->name }}</td>
                                <td class="px-6 py-4 text-right text-sm text-gray-700">Rp{{ number_format((float) $product->sale_price, 0, ',', '.') }}</td>
                                <td class="px-6 py-4 text-sm {{ $product->is_active ? 'text-green-700' : 'text-gray-500' }}">{{ $product->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <a href="{{ route('products.edit', $product) }}" class="text-indigo-700 hover:text-indigo-900">Ubah</a>
                                    <form method="POST" action="{{ route('products.status', $product) }}" class="inline ms-3">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="text-gray-600 hover:text-gray-900">{{ $product->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500">Belum ada produk.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $products->links() }}</div>
        </div>
    </div>
</x-app-layout>
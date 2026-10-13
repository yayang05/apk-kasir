<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Kategori Produk</h2>
            <a href="{{ route('categories.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">Tambah Kategori</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-auth-session-status class="mb-4" :status="session('status')" />

            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kategori</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Deskripsi</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($categories as $category)
                            <tr>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $category->name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $category->description ?: '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $category->products_count }}</td>
                                <td class="px-6 py-4 text-sm {{ $category->is_active ? 'text-green-700' : 'text-gray-500' }}">{{ $category->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <a href="{{ route('categories.edit', $category) }}" class="text-indigo-700 hover:text-indigo-900">Ubah</a>
                                    <form method="POST" action="{{ route('categories.status', $category) }}" class="inline ms-3">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="text-gray-600 hover:text-gray-900">{{ $category->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">Belum ada kategori.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $categories->links() }}</div>
        </div>
    </div>
</x-app-layout>
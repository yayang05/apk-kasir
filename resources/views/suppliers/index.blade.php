<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pemasok</h2>
            <a href="{{ route('suppliers.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">Tambah Pemasok</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-auth-session-status class="mb-4" :status="session('status')" />
            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kode</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pemasok</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kontak</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($suppliers as $supplier)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $supplier->code }}</td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $supplier->name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $supplier->contact_person ?: '-' }}{{ $supplier->phone ? ' · '.$supplier->phone : '' }}</td>
                                <td class="px-6 py-4 text-sm {{ $supplier->is_active ? 'text-green-700' : 'text-gray-500' }}">{{ $supplier->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <a href="{{ route('suppliers.edit', $supplier) }}" class="text-indigo-700 hover:text-indigo-900">Ubah</a>
                                    <form method="POST" action="{{ route('suppliers.status', $supplier) }}" class="inline ms-3">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="text-gray-600 hover:text-gray-900">{{ $supplier->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">Belum ada pemasok.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $suppliers->links() }}</div>
        </div>
    </div>
</x-app-layout>
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Manajemen Cabang</h2>
            <a href="{{ route('branches.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                Tambah Cabang
            </a>
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Cabang</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Alamat</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pengguna</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($branches as $branch)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $branch->code }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $branch->name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $branch->address ?: '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $branch->users_count }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span @class(['font-medium', 'text-green-700' => $branch->is_active, 'text-gray-500' => ! $branch->is_active])>
                                        {{ $branch->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <a href="{{ route('branches.edit', $branch) }}" class="text-indigo-700 hover:text-indigo-900">Ubah</a>
                                    <form method="POST" action="{{ route('branches.status', $branch) }}" class="inline ms-3">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="text-gray-600 hover:text-gray-900">
                                            {{ $branch->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500">Belum ada cabang.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $branches->links() }}</div>
        </div>
    </div>
</x-app-layout>
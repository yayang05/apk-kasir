<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pegawai</h2>
            <a href="{{ route('employees.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">Tambah Pegawai</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-auth-session-status class="mb-4" :status="session('status')" />

            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Penugasan Cabang</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($employees as $employee)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $employee->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $employee->email }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    @forelse ($employee->branches as $branch)
                                        <div>{{ $branch->name }} <span class="text-gray-500">({{ $branch->pivot->role === 'manager' ? 'Manajer' : 'Kasir' }})</span></div>
                                    @empty
                                        <span class="text-gray-500">Belum ditugaskan</span>
                                    @endforelse
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm"><a href="{{ route('employees.edit', $employee) }}" class="text-indigo-700 hover:text-indigo-900">Ubah</a><form method="POST" action="{{ route('employees.destroy', $employee) }}" class="ms-3 inline" onsubmit="return confirm('Hapus pegawai ini? Riwayat transaksi akan dicek terlebih dahulu.');">@csrf @method('DELETE')<button type="submit" class="text-red-600 hover:text-red-800">Hapus</button></form></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500">Belum ada pegawai.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $employees->links() }}</div>
        </div>
    </div>
</x-app-layout>

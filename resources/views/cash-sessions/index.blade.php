<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Shift Kas</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <x-auth-session-status class="mb-4" :status="session('status')" />

            @if ($errors->any())
                <div class="mb-5 border-s-4 border-red-600 bg-red-50 p-4 text-sm text-red-800" role="alert">
                    <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            @if ($openSession)
                <section class="mb-8 bg-white p-6 shadow-sm sm:rounded-lg">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-green-700">Shift sedang berjalan</p>
                            <h3 class="mt-1 text-lg font-semibold text-gray-900">{{ $openSession->branch->name }}</h3>
                            <p class="mt-1 text-sm text-gray-500">Dibuka {{ $openSession->opened_at->format('d/m/Y H:i') }}</p>
                        </div>
                        <a href="{{ route('sales.create', ['branch_id' => $openSession->branch_id]) }}" class="text-sm font-medium text-indigo-700 hover:text-indigo-900">Buka kasir</a>
                    </div>

                    <dl class="mt-6 grid gap-4 border-t border-gray-200 pt-5 sm:grid-cols-3">
                        <div><dt class="text-sm text-gray-500">Saldo Awal</dt><dd class="mt-1 font-semibold text-gray-900">Rp{{ number_format((float) $openSession->opening_cash, 0, ',', '.') }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Tunai Diterima Shift</dt><dd class="mt-1 font-semibold text-gray-900">Rp{{ number_format((float) $openCashTotal, 0, ',', '.') }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Kas Harapan</dt><dd class="mt-1 font-semibold text-gray-900">Rp{{ number_format((float) $openSession->opening_cash + (float) $openCashTotal, 0, ',', '.') }}</dd></div>
                    </dl>

                    <form method="POST" action="{{ route('cash-sessions.close', $openSession) }}" class="mt-6 grid gap-4 border-t border-gray-200 pt-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                        @csrf
                        @method('PATCH')
                        <div>
                            <x-input-label for="closing_cash" value="Kas Fisik Saat Tutup (Rp)" />
                            <x-text-input id="closing_cash" name="closing_cash" type="number" min="0" step="1" class="mt-1 block w-full" :value="old('closing_cash')" required />
                            <x-input-error :messages="$errors->get('closing_cash')" class="mt-2" />
                        </div>
                        <x-primary-button>Tutup Shift</x-primary-button>
                    </form>
                </section>
            @elseif ($branches->isNotEmpty())
                <section class="mb-8 bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-base font-semibold text-gray-900">Buka Shift Baru</h3>
                    <p class="mt-1 text-sm text-gray-500">Hitung uang modal di laci sebelum mulai bertransaksi.</p>
                    <form method="POST" action="{{ route('cash-sessions.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                        @csrf
                        <div>
                            <x-input-label for="branch_id" value="Cabang" />
                            <select id="branch_id" name="branch_id" required class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->code }} · {{ $branch->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="opening_cash" value="Saldo Awal Kas (Rp)" />
                            <x-text-input id="opening_cash" name="opening_cash" type="number" min="0" step="1" class="mt-1 block w-full" :value="old('opening_cash', 0)" required />
                            <x-input-error :messages="$errors->get('opening_cash')" class="mt-2" />
                        </div>
                        <div class="sm:col-span-2"><x-primary-button>Buka Shift</x-primary-button></div>
                    </form>
                </section>
            @else
                <section class="mb-8 bg-white p-6 text-sm text-gray-600 shadow-sm sm:rounded-lg">Tidak ada cabang aktif yang ditugaskan ke akun ini.</section>
            @endif

            <section>
                <h3 class="mb-3 text-base font-semibold text-gray-900">Riwayat Shift Saya</h3>
                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cabang / Dibuka</th>
                                <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Saldo Awal</th>
                                <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Kas Harapan</th>
                                <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Kas Fisik</th>
                                <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Selisih</th>
                                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($sessions as $session)
                                <tr>
                                    <td class="px-5 py-3 text-sm text-gray-700">{{ $session->branch->name }}<br><span class="text-xs text-gray-500">{{ $session->opened_at->format('d/m/Y H:i') }}</span></td>
                                    <td class="px-5 py-3 text-right text-sm text-gray-700">Rp{{ number_format((float) $session->opening_cash, 0, ',', '.') }}</td>
                                    <td class="px-5 py-3 text-right text-sm text-gray-700">{{ $session->expected_cash === null ? '-' : 'Rp'.number_format((float) $session->expected_cash, 0, ',', '.') }}</td>
                                    <td class="px-5 py-3 text-right text-sm text-gray-700">{{ $session->closing_cash === null ? '-' : 'Rp'.number_format((float) $session->closing_cash, 0, ',', '.') }}</td>
                                    <td class="px-5 py-3 text-right text-sm {{ (float) $session->difference < 0 ? 'text-red-700' : 'text-gray-700' }}">{{ $session->difference === null ? '-' : 'Rp'.number_format((float) $session->difference, 0, ',', '.') }}</td>
                                    <td class="px-5 py-3 text-sm {{ $session->closed_at ? 'text-gray-500' : 'font-medium text-green-700' }}">{{ $session->closed_at ? 'Ditutup '.$session->closed_at->format('d/m H:i') : 'Berjalan' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-gray-500">Belum ada riwayat shift.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $sessions->links() }}</div>
            </section>
        </div>
    </div>
</x-app-layout>
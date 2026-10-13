@php
    $money = fn ($value) => 'Rp'.number_format((float) $value, 0, ',', '.');
    $menus = ['penjualan' => 'Penjualan', 'pendapatan' => 'Pendapatan', 'laba-rugi' => 'Laba Rugi', 'neraca' => 'Neraca', 'kredit' => 'Kredit / Piutang', 'stok' => 'Stok'];
    $moneyColumns = ['penjualan' => [5, 6, 7], 'pendapatan' => [4], 'laba-rugi' => [1], 'neraca' => [1], 'kredit' => [4, 5, 6], 'stok' => [5, 6]][$report];
@endphp
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight print:hidden">{{ $titles[$report] }}</h2></x-slot>
    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-5 hidden items-start justify-between border-b border-gray-300 pb-4 print:flex">
                <div><h1 class="text-2xl font-bold">{{ $titles[$report] }}</h1><p class="mt-1 text-sm">Periode {{ \Carbon\CarbonImmutable::parse($dateFrom)->format('d/m/Y') }} s/d {{ \Carbon\CarbonImmutable::parse($dateTo)->format('d/m/Y') }}</p></div>
                <p class="text-right text-sm">Dicetak {{ now()->format('d/m/Y H:i') }}<br>{{ config('app.name', 'Apk Kasir') }}</p>
            </div>
            <div class="mb-5 flex flex-wrap gap-2 print:hidden">
                @foreach ($menus as $key => $label)
                    <a href="{{ route('reports.detail', ['report' => $key, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'branch_id' => $branchId]) }}" class="rounded-md px-3 py-2 text-sm font-semibold {{ $report === $key ? 'bg-indigo-600 text-white' : 'border border-gray-300 bg-white text-gray-700' }}">{{ $label }}</a>
                @endforeach
            </div>
            <form method="GET" action="{{ route('reports.detail', $report) }}" class="mb-6 flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm print:hidden">
                <div><x-input-label for="date_from" value="Dari Tanggal" /><x-text-input id="date_from" name="date_from" type="date" class="mt-1 block" :value="$dateFrom" required /></div>
                <div><x-input-label for="date_to" value="Sampai Tanggal" /><x-text-input id="date_to" name="date_to" type="date" class="mt-1 block" :value="$dateTo" required /></div>
                <div><x-input-label for="branch_id" value="Cabang" /><select id="branch_id" name="branch_id" class="mt-1 block rounded-md border-gray-300"><option value="">Semua cabang</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected((int) $branchId === $branch->id)>{{ $branch->code }} · {{ $branch->name }}</option>@endforeach</select></div>
                <button class="rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white">Tampilkan</button>
                <a href="{{ route('reports.detail.export', array_merge(['report' => $report], request()->query())) }}" class="rounded-md border border-green-300 bg-green-50 px-4 py-2.5 text-sm font-semibold text-green-800">Unduh Excel</a>
                <button type="button" onclick="window.print()" class="rounded-md bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white">Cetak / PDF</button>
            </form>
            <div class="mb-4 rounded-md border border-blue-100 bg-blue-50 p-3 text-sm text-blue-800 print:hidden">Excel diunduh dalam format CSV yang kompatibel dengan Microsoft Excel. Tombol Cetak / PDF membuka dialog cetak browser; pilih <b>Save as PDF</b> untuk menyimpan PDF.</div>
            <section class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50"><tr>@foreach ($data['headers'][0] as $header)<th class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $header }}</th>@endforeach</tr></thead>
                    <tbody class="divide-y divide-gray-200">
                    @forelse ($data['rows'] as $row)
                        <tr>@foreach ($row as $index => $value)<td class="whitespace-nowrap px-4 py-3 text-sm {{ in_array($index, $moneyColumns, true) ? 'text-right' : 'text-gray-700' }}">{{ in_array($index, $moneyColumns, true) ? $money($value) : $value }}</td>@endforeach</tr>
                    @empty
                        <tr><td colspan="{{ count($data['headers'][0]) }}" class="px-4 py-10 text-center text-sm text-gray-500">Tidak ada data pada periode dan cabang yang dipilih.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </section>
            @if ($report === 'laba-rugi' || $report === 'neraca')
                <p class="mt-3 text-xs text-gray-500">* Laba rugi dan neraca mengikuti data penjualan, HPP, pembayaran, piutang, dan persediaan yang tersedia di aplikasi. Beban operasional, hutang, dan modal belum memiliki modul transaksi tersendiri.</p>
            @endif
        </div>
    </div>
</x-app-layout>

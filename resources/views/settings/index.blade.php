<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm text-gray-500">Akun, tampilan, data, dan perangkat</p>
            <h2 class="mt-1 text-xl font-semibold text-gray-900">Pengaturan</h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8" x-data="settingsPreferences">
            <x-auth-session-status :status="session('status')" />

            @if ($errors->any())
                <div class="border-s-4 border-red-600 bg-red-50 p-4 text-sm text-red-800" role="alert">
                    <p class="font-semibold">Pengaturan tidak dapat disimpan.</p>
                    <ul class="mt-2 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="grid gap-5 lg:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">Tampilan</h3>
                    <p class="mt-1 text-sm text-gray-500">Preferensi disimpan di browser ini.</p>
                    <fieldset class="mt-4 flex flex-wrap gap-3">
                        <legend class="sr-only">Tema warna</legend>
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 has-[:checked]:border-teal-700 has-[:checked]:ring-1 has-[:checked]:ring-teal-700">
                            <input type="radio" name="theme" value="light" x-model="theme" @change="applyTheme" class="border-gray-300 text-teal-700 focus:ring-teal-600">
                            <span>Terang</span>
                        </label>
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 has-[:checked]:border-teal-700 has-[:checked]:ring-1 has-[:checked]:ring-teal-700">
                            <input type="radio" name="theme" value="dark" x-model="theme" @change="applyTheme" class="border-gray-300 text-teal-700 focus:ring-teal-600">
                            <span>Gelap</span>
                        </label>
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 has-[:checked]:border-teal-700 has-[:checked]:ring-1 has-[:checked]:ring-teal-700">
                            <input type="radio" name="theme" value="transparent" x-model="theme" @change="applyTheme" class="border-gray-300 text-teal-700 focus:ring-teal-600">
                            <span>Transparan</span>
                        </label>
                    </fieldset>
                    <div class="mt-5 max-w-xl">
                        <x-input-label for="theme_background" value="Gambar Latar Kustom" />
                        <input id="theme_background" type="file" accept="image/*" @change="selectBackground($event)" class="mt-1 block w-full rounded-md border border-gray-300 bg-white text-sm text-gray-700 file:mr-4 file:border-0 file:bg-gray-100 file:px-4 file:py-2.5 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200">
                        <p class="mt-2 text-xs text-gray-500">Pilih JPG, PNG, WebP, atau gambar lain hingga 8 MB. Gambar disimpan di browser ini saja.</p>
                        <div class="mt-3 flex flex-wrap items-center gap-3" x-show="backgroundName" x-cloak>
                            <span class="min-w-0 truncate text-sm text-gray-700" x-text="backgroundName"></span>
                            <button type="button" @click="removeBackground()" class="text-sm font-medium text-red-700 hover:text-red-900">Hapus Gambar</button>
                        </div>
                        <p class="mt-2 text-sm text-red-700" role="alert" x-show="backgroundError" x-text="backgroundError"></p>
                    </div>
                    <div class="mt-6 max-w-xl rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div><x-input-label for="sidebar_color" value="Warna menu navigasi" /><p class="mt-1 text-xs text-gray-500">Pilih warna topbar navigasi. Efek transparan dan blur tetap aktif.</p></div>
                            <input id="sidebar_color" type="color" x-model="sidebarColor" @input="applySidebar" class="h-10 w-14 cursor-pointer rounded border border-gray-300 bg-white p-1" aria-label="Pilih warna menu samping">
                        </div>
                        <label for="sidebar_opacity" class="mt-4 block text-sm font-medium text-gray-700">Kegelapan / transparansi <span class="font-normal text-gray-500" x-text="Math.round(sidebarOpacity * 100) + '%' "></span></label>
                        <input id="sidebar_opacity" type="range" min="0.55" max="0.98" step="0.01" x-model="sidebarOpacity" @input="applySidebar" class="mt-2 w-full accent-teal-600">
                    </div>
                    <div class="mt-4 max-w-xl rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div><x-input-label for="surface_color" value="Warna area konten" /><p class="mt-1 text-xs text-gray-500">Mengatur kartu dan panel putih di bagian bawah aplikasi.</p></div>
                            <input id="surface_color" type="color" x-model="surfaceColor" @input="applySurface" class="h-10 w-14 cursor-pointer rounded border border-gray-300 bg-white p-1" aria-label="Pilih warna area konten">
                        </div>
                        <label for="surface_opacity" class="mt-4 block text-sm font-medium text-gray-700">Transparansi area konten <span class="font-normal text-gray-500" x-text="Math.round(surfaceOpacity * 100) + '%' "></span></label>
                        <input id="surface_opacity" type="range" min="0.35" max="0.98" step="0.01" x-model="surfaceOpacity" @input="applySurface" class="mt-2 w-full accent-teal-600">
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">Cetak Struk</h3>
                    <p class="mt-1 text-sm text-gray-500">Pilih lebar kertas printer thermal. Perubahan berlaku saat mencetak struk.</p>
                    <fieldset class="mt-4 flex flex-wrap gap-3">
                        <legend class="sr-only">Lebar kertas</legend>
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 has-[:checked]:border-teal-700 has-[:checked]:ring-1 has-[:checked]:ring-teal-700">
                            <input type="radio" name="receipt-width" value="58" x-model="receiptWidth" @change="applyReceiptWidth" class="border-gray-300 text-teal-700 focus:ring-teal-600">
                            <span>58 mm</span>
                        </label>
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 has-[:checked]:border-teal-700 has-[:checked]:ring-1 has-[:checked]:ring-teal-700">
                            <input type="radio" name="receipt-width" value="80" x-model="receiptWidth" @change="applyReceiptWidth" class="border-gray-300 text-teal-700 focus:ring-teal-600">
                            <span>80 mm</span>
                        </label>
                    </fieldset>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Profil</h3>
                        <p class="mt-1 text-sm text-gray-500">Ubah nama, email login, atau password akun Anda.</p>
                    </div>
                    <a href="{{ route('profile.edit') }}" class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Buka Profil</a>
                </div>
            </section>

            @if ($isSuperAdmin)
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Backup Database</h3>
                        <p class="mt-1 max-w-3xl text-sm text-gray-500">Buat snapshot JSON tabel aplikasi. File disimpan privat dan langsung diunduh; simpan salinannya di lokasi aman. Backup berisi data akun, transaksi, dan informasi operasional.</p>
                    </div>
                    <a href="{{ route('settings.backup') }}" class="mt-4 inline-flex items-center rounded-md bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-700">Buat & Unduh Backup</a>
                    @if ($backupFiles->isNotEmpty())
                        <div class="mt-5 overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50"><tr>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">File Backup Privat</th>
                                    <th class="px-5 py-3 text-right text-xs font-medium uppercase text-gray-500">Ukuran</th>
                                    <th class="px-5 py-3 text-right text-xs font-medium uppercase text-gray-500">Aksi</th>
                                </tr></thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach ($backupFiles as $backup)
                                        <tr>
                                            <td class="px-5 py-3 text-sm text-gray-800">{{ $backup['name'] }}<br><span class="text-xs text-gray-500">{{ date('d/m/Y H:i', $backup['modified_at']) }}</span></td>
                                            <td class="px-5 py-3 text-right text-sm text-gray-600">{{ number_format($backup['size'] / 1024, 1, ',', '.') }} KB</td>
                                            <td class="px-5 py-3 text-right text-sm"><a href="{{ route('settings.backups.download', ['fileName' => $backup['name']]) }}" class="font-medium text-indigo-700 hover:text-indigo-900">Unduh</a></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Ekspor / Impor Produk</h3>
                            <p class="mt-1 max-w-3xl text-sm text-gray-500">Ekspor katalog ke CSV atau impor hingga 2.000 produk. Impor memperbarui produk dengan SKU yang sama, membuat kategori bila belum ada, dan stok produk baru dimulai dari nol di semua cabang.</p>
                        </div>
                        <a href="{{ route('settings.products.export') }}" class="inline-flex shrink-0 items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Ekspor CSV</a>
                    </div>
                    <form method="POST" action="{{ route('settings.products.import') }}" enctype="multipart/form-data" class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-end">
                        @csrf
                        <div class="w-full max-w-xl">
                            <x-input-label for="products_csv" value="File CSV Produk" />
                            <input id="products_csv" name="products_csv" type="file" accept=".csv,text/csv" required class="mt-1 block w-full rounded-md border border-gray-300 bg-white text-sm text-gray-700 file:mr-4 file:border-0 file:bg-gray-100 file:px-4 file:py-2.5 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200">
                            <p class="mt-2 text-xs text-gray-500">Kolom wajib: sku, name, category, unit, cost_price, sale_price. Kolom opsional: barcode, is_active.</p>
                            <x-input-error :messages="$errors->get('products_csv')" class="mt-2" />
                        </div>
                        <x-primary-button>Impor Produk</x-primary-button>
                    </form>
                </section>

                <section class="rounded-2xl border border-red-200 bg-red-50/40 p-6 shadow-sm">
                    <div class="max-w-3xl">
                        <h3 class="text-base font-semibold text-red-800">Reset Data Operasional</h3>
                        <p class="mt-1 text-sm text-gray-600">Menghapus seluruh transaksi, item transaksi, pembelian, shift kas, dan riwayat pergerakan stok; jumlah stok disetel ke nol. Akun, cabang, kategori, dan katalog produk tetap dipertahankan. Tindakan ini tidak dapat dibatalkan. Buat backup terlebih dahulu.</p>
                        <p class="mt-3 text-xs font-medium text-gray-600">Saat ini: {{ number_format($counts['sales']) }} transaksi · {{ number_format($counts['purchases']) }} pembelian · {{ number_format($counts['products']) }} produk · {{ number_format($counts['branches']) }} cabang.</p>
                    </div>
                    <form method="POST" action="{{ route('settings.reset-operational-data') }}" class="mt-5 grid max-w-3xl gap-4 sm:grid-cols-2">
                        @csrf
                        <div>
                            <x-input-label for="reset_password" value="Password Admin Saat Ini" />
                            <x-text-input id="reset_password" name="password" type="password" class="mt-1 block w-full" required autocomplete="current-password" />
                        </div>
                        <div>
                            <x-input-label for="reset_confirmation" value="Ketik RESET DATA OPERASIONAL" />
                            <x-text-input id="reset_confirmation" name="confirmation" type="text" class="mt-1 block w-full" required autocomplete="off" />
                        </div>
                        <div class="sm:col-span-2">
                            <button type="submit" class="inline-flex items-center rounded-md bg-red-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-800" onclick="return confirm('Reset semua transaksi, pembelian, shift, dan ledger stok? Pastikan backup sudah dibuat.')">Reset Data Operasional</button>
                        </div>
                    </form>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>

<nav x-data="{ open: false, reports: false, admin: false }" class="app-topbar">
    <div class="mx-auto max-w-[1600px] px-4 sm:px-6 lg:px-8">
        <div class="flex h-[4.5rem] items-center gap-4">
            <a href="{{ route('dashboard') }}" class="group flex shrink-0 items-center gap-3"><span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/20 transition group-hover:rotate-[-6deg] group-hover:bg-white/25"><x-application-logo class="h-6 w-auto fill-current text-white" /></span><span class="hidden sm:block"><span class="block text-sm font-extrabold tracking-wide text-white">Apk Kasir</span><span class="block text-[10px] font-medium uppercase tracking-[0.18em] text-white/50">Retail workspace</span></span></a>
            <div class="hidden h-7 w-px bg-white/15 lg:block"></div>
            <div class="hidden items-center gap-1 lg:flex">
                <a href="{{ route('dashboard') }}" class="topbar-link {{ request()->routeIs('dashboard') ? 'topbar-link-active' : '' }}">Dashboard</a><a href="{{ route('sales.create') }}" class="topbar-link {{ request()->routeIs('sales.create') ? 'topbar-link-active' : '' }}">Kasir</a><a href="{{ route('sales.index') }}" class="topbar-link {{ request()->routeIs('sales.*') && !request()->routeIs('sales.create') ? 'topbar-link-active' : '' }}">Transaksi</a>
                @if (Auth::user()->isSuperAdmin() || Auth::user()->branches()->wherePivot('role', 'manager')->exists())
                    <div class="relative" @click.outside="reports = false"><button type="button" @click="reports = !reports; admin = false" class="topbar-link {{ request()->routeIs('reports.*') ? 'topbar-link-active' : '' }}">Laporan <span class="ml-1 text-[10px]">▼</span></button><div x-show="reports" x-cloak class="topbar-dropdown left-0 w-56"><a href="{{ route('reports.index') }}" class="topbar-dropdown-link font-semibold">Ringkasan laporan</a>@foreach (['penjualan' => 'Penjualan', 'pendapatan' => 'Pendapatan', 'laba-rugi' => 'Laba Rugi', 'neraca' => 'Neraca', 'kredit' => 'Kredit / Piutang', 'stok' => 'Stok'] as $reportKey => $reportLabel)<a href="{{ route('reports.detail', $reportKey) }}" class="topbar-dropdown-link">{{ $reportLabel }}</a>@endforeach</div></div>
                    <a href="{{ route('inventory.index') }}" class="topbar-link {{ request()->routeIs('inventory.*') ? 'topbar-link-active' : '' }}">Persediaan</a>
                @endif
                <a href="{{ route('customers.index') }}" class="topbar-link {{ request()->routeIs('customers.*') ? 'topbar-link-active' : '' }}">Pelanggan</a>
                @if (Auth::user()->isSuperAdmin())<div class="relative" @click.outside="admin = false"><button type="button" @click="admin = !admin; reports = false" class="topbar-link">Administrasi <span class="ml-1 text-[10px]">▼</span></button><div x-show="admin" x-cloak class="topbar-dropdown right-0 w-48"><a href="{{ route('branches.index') }}" class="topbar-dropdown-link">Cabang</a><a href="{{ route('products.index') }}" class="topbar-dropdown-link">Produk</a><a href="{{ route('categories.index') }}" class="topbar-dropdown-link">Kategori</a><a href="{{ route('employees.index') }}" class="topbar-dropdown-link">Pegawai</a><a href="{{ route('suppliers.index') }}" class="topbar-dropdown-link">Pemasok</a></div></div>@endif
            </div>
            <div class="ml-auto flex items-center gap-2"><a href="{{ route('sales.create') }}" class="hidden rounded-xl bg-teal-400 px-3.5 py-2 text-xs font-bold text-slate-950 shadow-lg transition hover:-translate-y-0.5 hover:bg-teal-300 sm:inline-flex">+ Transaksi Baru</a><div class="relative hidden lg:block" @click.outside="open = false"><button type="button" @click="open = !open" class="flex items-center gap-2 rounded-xl px-2 py-1.5 text-left hover:bg-white/10"><span class="flex h-8 w-8 items-center justify-center rounded-full bg-white/15 text-xs font-bold text-white">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span><span class="max-w-28 truncate text-xs font-semibold text-white">{{ Auth::user()->name }}</span></button><div x-show="open" x-cloak class="topbar-dropdown right-0 w-52"><div class="border-b border-slate-100 px-4 py-3"><p class="text-xs font-bold text-slate-900">{{ Auth::user()->name }}</p><p class="truncate text-[11px] text-slate-500">{{ Auth::user()->email }}</p></div><a href="{{ route('settings.index') }}" class="topbar-dropdown-link">Pengaturan</a><a href="{{ route('profile.edit') }}" class="topbar-dropdown-link">Profil</a><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="topbar-dropdown-link w-full text-left text-red-600">Keluar</button></form></div></div><button @click="open = !open" type="button" class="rounded-xl p-2 text-white/80 hover:bg-white/10 lg:hidden" :aria-expanded="open.toString()" aria-label="Buka menu"><span class="text-xl">☰</span></button></div>
        </div>
        <div x-show="open" x-cloak class="border-t border-white/10 pb-4 pt-3 lg:hidden"><div class="grid grid-cols-2 gap-1 sm:grid-cols-3"><a href="{{ route('dashboard') }}" class="mobile-top-link">Dashboard</a><a href="{{ route('sales.create') }}" class="mobile-top-link">Kasir</a><a href="{{ route('sales.index') }}" class="mobile-top-link">Transaksi</a><a href="{{ route('customers.index') }}" class="mobile-top-link">Pelanggan</a><a href="{{ route('cash-sessions.index') }}" class="mobile-top-link">Shift Kas</a><a href="{{ route('settings.index') }}" class="mobile-top-link">Pengaturan</a>@if (Auth::user()->isSuperAdmin() || Auth::user()->branches()->wherePivot('role', 'manager')->exists())<a href="{{ route('reports.index') }}" class="mobile-top-link">Laporan</a><a href="{{ route('inventory.index') }}" class="mobile-top-link">Persediaan</a>@endif</div></div>
    </div>
</nav>

<nav x-data="{ open: false }" class="app-sidebar shrink-0 border-b border-slate-800 lg:sticky lg:top-0 lg:flex lg:h-screen lg:w-72 lg:flex-col lg:border-b-0 lg:border-r">
    <div class="hidden min-h-0 flex-1 flex-col lg:flex">
        <a href="{{ route('dashboard') }}" class="flex h-20 shrink-0 items-center gap-3 border-b border-slate-800 bg-[#0f172a] px-5">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-teal-400 to-cyan-500 shadow-lg shadow-cyan-950/40"><x-application-logo class="block h-6 w-auto fill-current text-white" /></span>
            <span><span class="block text-sm font-bold tracking-wide text-white">Apk Kasir</span><span class="block text-[11px] font-medium text-slate-400">Manajemen penjualan</span></span>
        </a>

        <div class="min-h-0 flex-1 space-y-7 overflow-y-auto px-3 py-6">
            <section class="space-y-1">
                <p class="px-3 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Menu utama</p>
                <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Dashboard</x-sidebar-link>
                <x-sidebar-link :href="route('sales.create')" :active="request()->routeIs('sales.create', 'sales.store')">Kasir</x-sidebar-link>
                <x-sidebar-link :href="route('sales.index')" :active="request()->routeIs('sales.index', 'sales.show')">Transaksi</x-sidebar-link>
                <x-sidebar-link :href="route('customers.index')" :active="request()->routeIs('customers.*')">Pelanggan / Member</x-sidebar-link>
                <x-sidebar-link :href="route('cash-sessions.index')" :active="request()->routeIs('cash-sessions.*')">Shift Kas</x-sidebar-link>
                @if (Auth::user()->isSuperAdmin() || Auth::user()->branches()->wherePivot('role', 'manager')->exists())
                    <x-sidebar-link :href="route('purchases.index')" :active="request()->routeIs('purchases.*')">Pembelian</x-sidebar-link>
                    <div x-data="{ reportsOpen: {{ request()->routeIs('reports.*') ? 'true' : 'false' }} }">
                        <div class="flex items-center rounded-lg {{ request()->routeIs('reports.*') ? 'bg-slate-800' : '' }}">
                            <a href="{{ route('reports.index') }}" class="min-w-0 flex-1 px-3 py-2 text-sm font-medium {{ request()->routeIs('reports.*') ? 'text-white' : 'text-slate-300 hover:text-white' }}">Laporan</a>
                            <button type="button" @click="reportsOpen = !reportsOpen" class="mr-1 rounded-md p-1.5 text-slate-500 hover:bg-slate-700 hover:text-white" :aria-expanded="reportsOpen.toString()" aria-label="Tampilkan laporan rinci">
                                <svg class="h-4 w-4 transition-transform" :class="reportsOpen ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" /></svg>
                            </button>
                        </div>
                        <div x-show="reportsOpen" x-cloak class="ml-3 mt-1 space-y-0.5 border-l border-slate-700 pl-2">
                            @foreach (['penjualan' => 'Penjualan', 'pendapatan' => 'Pendapatan', 'laba-rugi' => 'Laba Rugi', 'neraca' => 'Neraca', 'kredit' => 'Kredit / Piutang', 'stok' => 'Stok'] as $reportKey => $reportLabel)
                                <a href="{{ route('reports.detail', $reportKey) }}" class="block rounded-md px-3 py-1.5 text-xs font-medium transition {{ request()->route('report') === $reportKey ? 'bg-teal-500/15 text-teal-300' : 'text-slate-400 hover:bg-slate-800 hover:text-white' }}">{{ $reportLabel }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if (Auth::user()->isSuperAdmin() || Auth::user()->branches()->wherePivot('role', 'manager')->exists())
                    <x-sidebar-link :href="route('inventory.index')" :active="request()->routeIs('inventory.*')">Persediaan</x-sidebar-link>
                @endif
            </section>

            @if (Auth::user()->isSuperAdmin())
                <section class="space-y-1">
                    <p class="px-3 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Administrasi</p>
                    <x-sidebar-link :href="route('branches.index')" :active="request()->routeIs('branches.*')">Cabang</x-sidebar-link>
                    <x-sidebar-link :href="route('products.index')" :active="request()->routeIs('products.*')">Produk</x-sidebar-link>
                    <x-sidebar-link :href="route('categories.index')" :active="request()->routeIs('categories.*')">Kategori</x-sidebar-link>
                    <x-sidebar-link :href="route('employees.index')" :active="request()->routeIs('employees.*')">Pegawai</x-sidebar-link>
                    <x-sidebar-link :href="route('suppliers.index')" :active="request()->routeIs('suppliers.*')">Pemasok</x-sidebar-link>
                </section>
            @endif

            <section class="space-y-1">
                <p class="px-3 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Akun</p>
                <x-sidebar-link :href="route('settings.index')" :active="request()->routeIs('settings.*')">Pengaturan</x-sidebar-link>
                <x-sidebar-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')">Profil</x-sidebar-link>
            </section>
        </div>

        <div class="shrink-0 border-t border-slate-800 bg-[#0f172a] p-4">
            <div class="flex items-center gap-3 rounded-xl bg-slate-800/70 p-3"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-teal-400/20 text-sm font-bold text-teal-300">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span><div class="min-w-0"><p class="truncate text-sm font-semibold text-white">{{ Auth::user()->name }}</p><p class="truncate text-[11px] text-slate-400">{{ Auth::user()->email }}</p></div></div>
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button type="submit" class="text-sm font-medium text-gray-600 hover:text-gray-900">Keluar</button>
            </form>
        </div>
    </div>

    <div class="lg:hidden">
        <div class="flex h-16 items-center justify-between bg-white px-4 shadow-sm sm:px-6">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-teal-500 to-cyan-600"><x-application-logo class="block h-5 w-auto fill-current text-white" /></span>
                <span class="text-sm font-bold text-slate-900">Apk Kasir</span>
            </a>
            <button @click="open = !open" type="button" class="inline-flex items-center justify-center rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-800" :aria-expanded="open.toString()" aria-label="Buka menu">
                <svg x-show="!open" class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <svg x-show="open" x-cloak class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 6 12 12M18 6 6 18" />
                </svg>
            </button>
        </div>

        <div x-show="open" x-cloak class="border-t border-gray-200 px-3 py-3">
            <div class="space-y-1">
                <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Dashboard</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('sales.create')" :active="request()->routeIs('sales.create', 'sales.store')">Kasir</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('sales.index')" :active="request()->routeIs('sales.index', 'sales.show')">Transaksi</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('customers.index')" :active="request()->routeIs('customers.*')">Pelanggan / Member</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('cash-sessions.index')" :active="request()->routeIs('cash-sessions.*')">Shift Kas</x-responsive-nav-link>
                @if (Auth::user()->isSuperAdmin() || Auth::user()->branches()->wherePivot('role', 'manager')->exists())
                    <x-responsive-nav-link :href="route('purchases.index')" :active="request()->routeIs('purchases.*')">Pembelian</x-responsive-nav-link>
                    <div x-data="{ reportsOpen: {{ request()->routeIs('reports.*') ? 'true' : 'false' }} }" class="rounded-lg">
                        <div class="flex items-center rounded-lg {{ request()->routeIs('reports.*') ? 'bg-slate-100' : '' }}">
                            <a href="{{ route('reports.index') }}" class="min-w-0 flex-1 px-3 py-2 text-base font-medium {{ request()->routeIs('reports.*') ? 'text-slate-900' : 'text-slate-600' }}">Laporan</a>
                            <button type="button" @click="reportsOpen = !reportsOpen" class="mr-1 rounded-md p-2 text-slate-400" :aria-expanded="reportsOpen.toString()" aria-label="Tampilkan laporan rinci"><svg class="h-4 w-4 transition-transform" :class="reportsOpen ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" /></svg></button>
                        </div>
                        <div x-show="reportsOpen" x-cloak class="ml-4 mt-1 space-y-0.5 border-l border-slate-200 pl-2">
                            @foreach (['penjualan' => 'Penjualan', 'pendapatan' => 'Pendapatan', 'laba-rugi' => 'Laba Rugi', 'neraca' => 'Neraca', 'kredit' => 'Kredit / Piutang', 'stok' => 'Stok'] as $reportKey => $reportLabel)
                                <a href="{{ route('reports.detail', $reportKey) }}" class="block rounded-md px-3 py-1.5 text-sm {{ request()->route('report') === $reportKey ? 'bg-slate-200 text-slate-900' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900' }}">{{ $reportLabel }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if (Auth::user()->isSuperAdmin() || Auth::user()->branches()->wherePivot('role', 'manager')->exists())
                    <x-responsive-nav-link :href="route('inventory.index')" :active="request()->routeIs('inventory.*')">Persediaan</x-responsive-nav-link>
                @endif
                @if (Auth::user()->isSuperAdmin())
                    <x-responsive-nav-link :href="route('branches.index')" :active="request()->routeIs('branches.*')">Cabang</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('products.index')" :active="request()->routeIs('products.*')">Produk</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('categories.index')" :active="request()->routeIs('categories.*')">Kategori</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('employees.index')" :active="request()->routeIs('employees.*')">Pegawai</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('suppliers.index')" :active="request()->routeIs('suppliers.*')">Pemasok</x-responsive-nav-link>
                @endif
                <x-responsive-nav-link :href="route('settings.index')" :active="request()->routeIs('settings.*')">Pengaturan</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')">Profil</x-responsive-nav-link>
            </div>

            <div class="mt-3 border-t border-gray-200 px-2 pt-3">
                <p class="text-sm font-semibold text-gray-900">{{ Auth::user()->name }}</p>
                <p class="text-xs text-gray-500">{{ Auth::user()->email }}</p>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="text-sm font-medium text-gray-600 hover:text-gray-900">Keluar</button>
                </form>
            </div>
        </div>
    </div>
</nav>

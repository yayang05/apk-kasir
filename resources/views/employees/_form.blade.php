<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($editing)
        @method('PUT')
    @endif

    <div>
        <x-input-label for="name" value="Nama Pegawai" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $employee?->name)" required maxlength="255" />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="email" value="Email Login" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $employee?->email)" required maxlength="255" autocomplete="username" />
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <x-input-label for="password" :value="$editing ? 'Password Baru (opsional)' : 'Password'" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" :required="! $editing" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="password_confirmation" value="Konfirmasi Password" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" :required="! $editing" autocomplete="new-password" />
        </div>
    </div>

    <fieldset class="space-y-3">
        <legend class="text-sm font-medium text-gray-700">Akses Cabang dan Peran</legend>
        <x-input-error :messages="$errors->get('branch_roles')" />
        @forelse ($branches as $branch)
            @php
                $membership = $employee?->branches->firstWhere('id', $branch->id);
                $assignment = old('branch_roles.'.$branch->id, []);
                $assigned = $assignment['enabled'] ?? (bool) $membership;
                $role = $assignment['role'] ?? $membership?->pivot?->role ?? 'cashier';
            @endphp
            <div class="grid gap-3 border-t border-gray-200 pt-3 sm:grid-cols-[minmax(0,1fr)_12rem] sm:items-center">
                <label for="branch-{{ $branch->id }}" class="inline-flex items-center gap-3">
                    <input id="branch-{{ $branch->id }}" type="checkbox" name="branch_roles[{{ $branch->id }}][enabled]" value="1" @checked($assigned) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <span class="text-sm text-gray-800">{{ $branch->code }} · {{ $branch->name }}</span>
                </label>
                <select name="branch_roles[{{ $branch->id }}][role]" class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" aria-label="Peran di {{ $branch->name }}">
                    <option value="cashier" @selected($role === 'cashier')>Kasir</option>
                    <option value="manager" @selected($role === 'manager')>Manajer</option>
                </select>
            </div>
        @empty
            <p class="border-t border-gray-200 pt-3 text-sm text-gray-500">Aktifkan cabang terlebih dahulu sebelum menambahkan pegawai.</p>
        @endforelse
    </fieldset>

    <div class="flex items-center gap-3">
        <x-primary-button>{{ $editing ? 'Simpan Perubahan' : 'Buat Akun Pegawai' }}</x-primary-button>
        <a href="{{ route('employees.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
    </div>
</form>
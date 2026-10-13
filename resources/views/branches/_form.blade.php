<form method="POST" action="{{ $action }}" class="space-y-5">
    @csrf
    @if ($editing)
        @method('PUT')
    @endif

    <div>
        <x-input-label for="code" value="Kode Cabang" />
        <x-text-input id="code" name="code" type="text" class="mt-1 block w-full" :value="old('code', $branch?->code)" required maxlength="30" />
        <x-input-error :messages="$errors->get('code')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="name" value="Nama Cabang" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $branch?->name)" required maxlength="255" />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="address" value="Alamat" />
        <x-text-input id="address" name="address" type="text" class="mt-1 block w-full" :value="old('address', $branch?->address)" maxlength="255" />
        <x-input-error :messages="$errors->get('address')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="phone" value="Nomor Telepon" />
        <x-text-input id="phone" name="phone" type="tel" class="mt-1 block w-full" :value="old('phone', $branch?->phone)" maxlength="30" />
        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
    </div>

    <div class="flex items-center gap-3">
        <x-primary-button>{{ $editing ? 'Simpan Perubahan' : 'Simpan Cabang' }}</x-primary-button>
        <a href="{{ route('branches.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
    </div>
</form>
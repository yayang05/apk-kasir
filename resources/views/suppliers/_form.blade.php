<form method="POST" action="{{ $action }}" class="space-y-5">
    @csrf
    @if ($editing)
        @method('PUT')
    @endif

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <x-input-label for="code" value="Kode Pemasok" />
            <x-text-input id="code" name="code" type="text" class="mt-1 block w-full" :value="old('code', $supplier?->code)" required maxlength="30" />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="name" value="Nama Pemasok" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $supplier?->name)" required maxlength="255" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <x-input-label for="contact_person" value="Nama Kontak" />
            <x-text-input id="contact_person" name="contact_person" type="text" class="mt-1 block w-full" :value="old('contact_person', $supplier?->contact_person)" maxlength="255" />
            <x-input-error :messages="$errors->get('contact_person')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="phone" value="Telepon" />
            <x-text-input id="phone" name="phone" type="tel" class="mt-1 block w-full" :value="old('phone', $supplier?->phone)" maxlength="30" />
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
        </div>
    </div>

    <div>
        <x-input-label for="email" value="Email" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $supplier?->email)" maxlength="255" />
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="address" value="Alamat" />
        <x-text-input id="address" name="address" type="text" class="mt-1 block w-full" :value="old('address', $supplier?->address)" maxlength="255" />
        <x-input-error :messages="$errors->get('address')" class="mt-2" />
    </div>

    <div class="flex items-center gap-3">
        <x-primary-button>{{ $editing ? 'Simpan Perubahan' : 'Simpan Pemasok' }}</x-primary-button>
        <a href="{{ route('suppliers.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
    </div>
</form>
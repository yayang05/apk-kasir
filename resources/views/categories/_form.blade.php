<form method="POST" action="{{ $action }}" class="space-y-5">
    @csrf
    @if ($editing)
        @method('PUT')
    @endif

    <div>
        <x-input-label for="name" value="Nama Kategori" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $category?->name)" required maxlength="255" />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="description" value="Deskripsi" />
        <x-text-input id="description" name="description" type="text" class="mt-1 block w-full" :value="old('description', $category?->description)" maxlength="255" />
        <x-input-error :messages="$errors->get('description')" class="mt-2" />
    </div>

    <div class="flex items-center gap-3">
        <x-primary-button>{{ $editing ? 'Simpan Perubahan' : 'Simpan Kategori' }}</x-primary-button>
        <a href="{{ route('categories.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
    </div>
</form>
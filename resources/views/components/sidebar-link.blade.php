@props(['active' => false])

<a {{ $attributes->class([
    'flex min-h-10 items-center rounded-md px-3 py-2 text-sm font-medium transition-colors',
    'bg-teal-500 text-white shadow-md shadow-teal-950/20' => $active,
    'text-slate-300 hover:bg-slate-800 hover:text-white' => ! $active,
]) }}>
    {{ $slot }}
</a>

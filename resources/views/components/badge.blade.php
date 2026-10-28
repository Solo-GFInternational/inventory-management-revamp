@props(['classes' => 'bg-slate-100 text-slate-700 ring-slate-200'])

<span {{ $attributes->class(['inline-flex items-center rounded-md px-2.5 py-1 text-xs font-medium ring-1 ring-inset', $classes]) }}>
    {{ $slot }}
</span>

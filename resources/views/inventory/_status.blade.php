{{-- The status badge for an inventory row, plus "Returned to stock" while it waits for a new holder. --}}
@if ($inventory->status === 'approved')
    <x-badge classes="bg-green-50 text-green-700 ring-green-600/20">Approved</x-badge>
@elseif ($inventory->approval_type === 'edit')
    <x-badge classes="bg-amber-50 text-amber-700 ring-amber-600/20">Edit – awaiting approval</x-badge>
@else
    <x-badge classes="bg-amber-50 text-amber-700 ring-amber-600/20">New – awaiting approval</x-badge>
@endif
@if ($inventory->isReturned())
    <x-badge classes="bg-sky-50 text-sky-700 ring-sky-600/20">Returned to stock</x-badge>
@endif

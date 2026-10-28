<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Stock;
use App\Services\StockLedger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    /** The fields a user fills in; also the fields compared for an edit's change log. */
    private const FIELDS = ['date', 'qty', 'item', 'serial_number', 'condition', 'taken_by', 'department', 'site'];

    public function __construct(private StockLedger $ledger) {}

    public function index(Request $request)
    {
        $filters = [
            'taken_by' => trim((string) $request->query('taken_by', '')),
            'site' => (string) $request->query('site', ''),
            'status' => (string) $request->query('status', ''),
        ];

        $inventories = Inventory::active()
            ->with('giver')
            ->when($filters['taken_by'] !== '', fn ($q) => $q->where('taken_by', 'like', '%'.$filters['taken_by'].'%'))
            ->when($filters['site'] !== '', fn ($q) => $q->where('site', $filters['site']))
            ->when(in_array($filters['status'], ['approved', 'pending'], true), fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('inventory.index', [
            'inventories' => $inventories,
            'filters' => $filters,
            'stocks' => Stock::active()->orderBy('item')->get(['item', 'qty']),
            'departments' => config('inventory.departments'),
            'sites' => config('inventory.sites'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $isAdmin = $request->user()->isAdmin();

        $inventory = new Inventory($data + [
            'given_by' => $request->user()->id,
            'status' => $isAdmin ? 'approved' : 'pending',
            'approval_type' => $isAdmin ? null : 'new',
        ]);

        // Approved rows take their stock now; pending ones on approval.
        $this->ledger->save($inventory);

        return redirect()->route('inventory.index')->with('status', $isAdmin
            ? 'Inventory added and stock updated.'
            : 'Inventory submitted for approval. Stock is deducted once an administrator approves it.');
    }

    public function update(Request $request, Inventory $inventory)
    {
        abort_unless($inventory->active_yn, 404);

        $data = $this->validated($request, $inventory);

        $before = $inventory->only(self::FIELDS);
        $inventory->fill($data);

        $changes = [];
        foreach (self::FIELDS as $field) {
            if ($this->normalise($field, $before[$field]) !== $this->normalise($field, $inventory->$field)) {
                $changes[$field] = ['old' => $this->normalise($field, $before[$field]), 'new' => $this->normalise($field, $inventory->$field)];
            }
        }

        if (! $changes) {
            return redirect()->route('inventory.index')->with('status', 'No changes to save.');
        }

        $isAdmin = $request->user()->isAdmin();

        if (! $isAdmin) {
            // A non-admin's edit goes back to the approval queue. A pending
            // new row stays a "new" approval; otherwise it becomes an "edit"
            // approval, keeping the original value of each changed field.
            if ($inventory->approval_type !== 'new') {
                $inventory->change_log = $this->mergeChangeLog($inventory->change_log ?? [], $changes);
                $inventory->approval_type = 'edit';
            }
            $inventory->status = 'pending';
            $inventory->edited_by = $request->user()->id;
        }

        // Admin edits of an approved row move the difference in stock; a
        // row sent back to pending returns its stock until it is approved.
        $this->ledger->save($inventory);

        return redirect()->route('inventory.index')->with('status', $isAdmin
            ? 'Inventory updated.'
            : 'Your changes were saved and sent to an administrator for approval.');
    }

    public function pending()
    {
        $inventories = Inventory::active()
            ->pending()
            ->with(['giver', 'editor'])
            ->orderBy('updated_at')
            ->get();

        return view('inventory.pending', compact('inventories'));
    }

    public function approve(Inventory $inventory)
    {
        if (! $inventory->active_yn) {
            return back()->with('error', 'This inventory record has been deactivated.');
        }

        if (! $inventory->isPending()) {
            return back()->with('error', 'This inventory record is already approved.');
        }

        try {
            DB::transaction(function () use ($inventory) {
                $inventory->status = 'approved';
                $inventory->approval_type = null;
                $inventory->change_log = null;

                // Re-checks the shelf now and takes the stock for new items.
                $this->ledger->save($inventory);

                activity('inventory')->performedOn($inventory)->event('approved')->log('Approved');
            });
        } catch (ValidationException $e) {
            $inventory->refresh();

            return back()->with('error', 'Cannot approve: '.$e->validator->errors()->first('qty'));
        }

        return back()->with('status', $inventory->condition === 'new'
            ? 'Inventory approved and stock updated.'
            : 'Inventory approved.');
    }

    public function destroy(Inventory $inventory)
    {
        if (! $inventory->active_yn) {
            return redirect()->route('inventory.index')->with('error', 'This inventory record is already deactivated.');
        }

        // An issued new item goes back on the shelf.
        $inventory->active_yn = false;
        $this->ledger->save($inventory);

        return redirect()->route('inventory.index')->with('status', 'Inventory deactivated.');
    }

    public function downloadPdf(Request $request)
    {
        $year = (int) ($request->query('year') ?: now()->year);

        $inventories = Inventory::active()
            ->with(['giver', 'editor'])
            ->whereYear('date', $year)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        return Pdf::loadView('inventory.pdf', compact('inventories', 'year'))
            ->setPaper('A4', 'landscape')
            ->download("inventory_{$year}.pdf");
    }

    /**
     * Validate the create/edit form, including that a new-condition item
     * doesn't ask for more than is on the shelf.
     */
    private function validated(Request $request, ?Inventory $inventory = null): array
    {
        // An old row may hold a department or site that's no longer listed.
        $departments = array_filter([...config('inventory.departments'), $inventory?->department]);
        $sites = array_filter([...config('inventory.sites'), $inventory?->site]);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'item' => ['required', 'string', 'max:255'],
            'qty' => ['required', 'integer', 'min:1'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'condition' => ['required', Rule::in(['new', 'used'])],
            'taken_by' => ['required', 'string', 'max:255'],
            'department' => ['required', Rule::in($departments)],
            'site' => ['required', Rule::in($sites)],
        ]);

        if ($data['condition'] === 'new') {
            $available = $this->ledger->available($data['item'], $inventory);

            if ((int) $data['qty'] > $available) {
                throw ValidationException::withMessages([
                    'qty' => $this->ledger->shortfallMessage($data['item'], $available, (int) $data['qty']),
                ]);
            }
        }

        $data['qty'] = (int) $data['qty'];

        return $data;
    }

    /** Compare and log values as plain strings (dates as Y-m-d, blanks as null). */
    private function normalise(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($field === 'date') {
            return substr((string) $value, 0, 10);
        }

        return (string) $value;
    }

    /** Fold a new diff into an existing one, keeping each field's original value. */
    private function mergeChangeLog(array $existing, array $changes): array
    {
        foreach ($changes as $field => $change) {
            $old = array_key_exists($field, $existing) ? $existing[$field]['old'] : $change['old'];
            $existing[$field] = ['old' => $old, 'new' => $change['new']];

            if ($old === $change['new']) {
                unset($existing[$field]);
            }
        }

        return $existing;
    }
}

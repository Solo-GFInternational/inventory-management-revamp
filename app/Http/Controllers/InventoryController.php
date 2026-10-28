<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Stock;
use App\Models\Type;
use App\Services\StockLedger;
use Barryvdh\DomPDF\Facade\Pdf;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    /** The fields a user fills in; also the fields compared for an edit's change log. */
    private const FIELDS = ['date', 'qty', 'stock_id', 'serial_number', 'condition', 'taken_by', 'department_id', 'site_id'];

    /**
     * Foreign-key fields and the key they are stored under in change_log. The
     * change log records names (item, department, site), not ids, so the
     * approvals page shows what the user saw when they made the edit.
     */
    private const LOG_KEYS = ['stock_id' => 'item', 'department_id' => 'department', 'site_id' => 'site'];

    public function __construct(private StockLedger $ledger) {}

    public function index(Request $request)
    {
        $filters = [
            'taken_by' => trim((string) $request->query('taken_by', '')),
            'site_id' => ctype_digit((string) $request->query('site_id', '')) ? (string) $request->query('site_id') : '',
            'status' => (string) $request->query('status', ''),
        ];

        $inventories = Inventory::active()
            ->with(['giver', 'stock', 'department', 'site'])
            ->when($filters['taken_by'] !== '', fn ($q) => $q->where('taken_by', 'like', '%'.$filters['taken_by'].'%'))
            ->when($filters['site_id'] !== '', fn ($q) => $q->where('site_id', (int) $filters['site_id']))
            ->when(in_array($filters['status'], ['approved', 'pending'], true), fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('inventory.index', [
            'inventories' => $inventories,
            'filters' => $filters,
            'stocks' => Stock::active()->orderBy('item')->get(['id', 'item', 'qty']),
            'departments' => Type::options(Type::DEPARTMENT),
            'sites' => Type::options(Type::SITE),
            // The filter keeps a site that has since been deactivated selectable.
            'filterSites' => Type::options(Type::SITE, $filters['site_id'] !== '' ? (int) $filters['site_id'] : null),
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
            $old = $this->normalise($field, $before[$field]);
            $new = $this->normalise($field, $inventory->$field);

            if ($old !== $new) {
                $changes[self::LOG_KEYS[$field] ?? $field] = [
                    'old' => $this->displayValue($field, $old),
                    'new' => $this->displayValue($field, $new),
                ];
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
            ->with(['giver', 'editor', 'stock', 'department', 'site'])
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
            ->with(['giver', 'stock', 'department', 'site'])
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
     * doesn't ask for more than is on the shelf. Items, departments and sites
     * must be active, except that a row being edited may keep the one it has.
     */
    private function validated(Request $request, ?Inventory $inventory = null): array
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'stock_id' => ['required', 'integer',
                Rule::exists(Stock::class, 'id')->where($this->activeOr($inventory?->stock_id))],
            'qty' => ['required', 'integer', 'min:1'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'condition' => ['required', Rule::in(['new', 'used'])],
            'taken_by' => ['required', 'string', 'max:255'],
            'department_id' => ['required', 'integer',
                Rule::exists(Type::class, 'id')->where('type_group', Type::DEPARTMENT)->where($this->activeOr($inventory?->department_id))],
            'site_id' => ['required', 'integer',
                Rule::exists(Type::class, 'id')->where('type_group', Type::SITE)->where($this->activeOr($inventory?->site_id))],
        ], [
            'stock_id.exists' => 'The chosen item is no longer in the stock list.',
            'department_id.exists' => 'The chosen department is no longer available.',
            'site_id.exists' => 'The chosen site is no longer available.',
        ], [
            'stock_id' => 'item',
            'department_id' => 'department',
            'site_id' => 'site',
        ]);

        foreach (['qty', 'stock_id', 'department_id', 'site_id'] as $field) {
            $data[$field] = (int) $data[$field];
        }

        if ($data['condition'] === 'new') {
            $available = $this->ledger->available($data['stock_id'], $inventory);

            if ($data['qty'] > $available) {
                $item = (string) Stock::whereKey($data['stock_id'])->value('item');

                throw ValidationException::withMessages([
                    'qty' => $this->ledger->shortfallMessage($item, $available, $data['qty']),
                ]);
            }
        }

        return $data;
    }

    /** An exists-rule condition: the row is active, or is the one the record already holds. */
    private function activeOr(mixed $keepId): Closure
    {
        return fn ($query) => $query->where('active_yn', true)
            ->when($keepId, fn ($query) => $query->orWhere('id', $keepId));
    }

    /** The name a foreign-key value is shown and logged as (other fields as they are). */
    private function displayValue(string $field, ?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $name = match ($field) {
            'stock_id' => Stock::whereKey($value)->value('item'),
            'department_id', 'site_id' => Type::whereKey($value)->value('type_name'),
            default => $value,
        };

        return $name ?? $value;
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

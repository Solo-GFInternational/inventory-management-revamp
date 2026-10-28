<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Stock;
use App\Models\Type;
use App\Services\BarcodeLabel;
use App\Services\StockLedger;
use App\Support\InventoryChangeLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    /**
     * The fields a user fills in; also the fields compared for an edit's
     * change log. Each record is one unit, so qty isn't among them.
     */
    private const FIELDS = ['date', 'stock_id', 'serial_number', 'condition', 'taken_by', 'department_id', 'site_id'];

    /** The fields entered once on the create form and shared by every record it adds. */
    private const SHARED_FIELDS = ['date', 'condition', 'stock_id', 'site_id'];

    /** The most people (records) one create form may add. */
    public const MAX_PEOPLE = 50;

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
            ->when($filters['status'] === 'returned', fn ($q) => $q->whereNotNull('returned_at'))
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

    /**
     * The create form adds one single-unit record per person receiving the
     * item, all sharing the date, condition, item and site. All are saved or
     * none: a stock shortfall part-way through rolls the whole batch back.
     */
    public function store(Request $request)
    {
        [$shared, $people] = $this->validatedBatch($request);
        $isAdmin = $request->user()->isAdmin();

        try {
            DB::transaction(function () use ($shared, $people, $isAdmin, $request) {
                foreach ($people as $person) {
                    $inventory = new Inventory($shared + $person + [
                        'qty' => 1,
                        'given_by' => $request->user()->id,
                        'status' => $isAdmin ? 'approved' : 'pending',
                        'approval_type' => $isAdmin ? null : 'new',
                    ]);

                    // Approved rows take their stock now; pending ones on approval.
                    // The barcode is assigned as the row is created (Inventory::booted).
                    $this->ledger->save($inventory);
                }
            });
        } catch (ValidationException $e) {
            // Stock taken by someone else since the check above: shown like it.
            throw ValidationException::withMessages(['people' => $e->validator->errors()->first('qty')]);
        }

        $count = count($people);
        $records = $count === 1 ? '1 inventory record' : "{$count} inventory records";

        return redirect()->route('inventory.index')->with('status', $isAdmin
            ? "{$records} added and stock updated."
            : "{$records} submitted for approval. Stock is deducted once an administrator approves them.");
    }

    /** A record's details, read-only. Where a scanned barcode leads; deactivated records are still shown. */
    public function show(Inventory $inventory)
    {
        $inventory->load(['giver', 'stock', 'department', 'site', 'creator',
            // The Timeline card, newest first (id breaks ties within a second).
            'updates' => fn ($query) => $query->active()->with('author')->latest()->latest('id'),
        ]);

        return view('inventory.show', compact('inventory'));
    }

    /**
     * The record's barcode label JPEG: a download, or with ?inline=1 shown in
     * the page. A label file that has gone missing is generated again.
     */
    public function barcode(Request $request, Inventory $inventory, BarcodeLabel $labels)
    {
        // Only a row created before barcodes existed and not yet backfilled has none.
        $inventory->assignBarcode();
        $labels->ensure($inventory);

        $disk = Storage::disk('local');
        $headers = ['Content-Type' => 'image/jpeg'];
        $filename = $inventory->barcode.'.jpg';

        return $request->boolean('inline')
            ? $disk->response($inventory->barcodePath(), $filename, $headers)
            : $disk->download($inventory->barcodePath(), $filename, $headers);
    }

    public function update(Request $request, Inventory $inventory)
    {
        abort_unless($inventory->active_yn, 404);

        $data = $this->validated($request, $inventory);

        $before = $inventory->only(self::FIELDS);
        $inventory->fill($data);

        $changes = InventoryChangeLog::diff(self::FIELDS, $before, $inventory->only(self::FIELDS));

        if (! $changes) {
            return redirect()->route('inventory.index')->with('status', 'No changes to save.');
        }

        $isAdmin = $request->user()->isAdmin();

        if (! $isAdmin) {
            // A non-admin's edit goes back to the approval queue. A pending
            // new row stays a "new" approval; otherwise it becomes an "edit"
            // approval, keeping the original value of each changed field.
            if ($inventory->approval_type !== 'new') {
                $inventory->change_log = InventoryChangeLog::merge($inventory->change_log ?? [], $changes);
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
     * Validate the create form: the shared fields once, then one entry per
     * person (taken by, department, serial number). For a new-condition item,
     * there must be one in stock for each person.
     *
     * @return array{0: array, 1: list<array>} the shared fields, and each person's fields
     */
    private function validatedBatch(Request $request): array
    {
        $data = $request->validate([
            ...$this->sharedRules(),
            'people' => ['required', 'array', 'min:1', 'max:'.self::MAX_PEOPLE],
            ...$this->personRules('people.*.'),
        ], [
            ...$this->messages(),
            'people.required' => 'Add at least one person receiving the item.',
            'people.max' => 'No more than '.self::MAX_PEOPLE.' people can be added at once.',
            'people.*.department_id.exists' => 'The department chosen for person :position is no longer available.',
        ], [
            ...$this->attributes(),
            'people.*.taken_by' => 'taken by (person :position)',
            'people.*.department_id' => 'department (person :position)',
            'people.*.serial_number' => 'serial number (person :position)',
        ]);

        $shared = $this->castIds(array_intersect_key($data, array_flip(self::SHARED_FIELDS)));

        $people = array_map(fn (array $person) => $this->castIds([
            'taken_by' => $person['taken_by'],
            'department_id' => $person['department_id'],
            'serial_number' => $person['serial_number'] ?? null,
        ]), array_values($data['people']));

        if ($shared['condition'] === 'new') {
            $this->ensureInStock($shared['stock_id'], count($people), null, 'people');
        }

        return [$shared, $people];
    }

    /**
     * Validate the edit form, including that a new-condition item is still
     * on the shelf. The record keeps its quantity (one unit).
     */
    private function validated(Request $request, Inventory $inventory): array
    {
        $data = $this->castIds($request->validate(
            [...$this->sharedRules($inventory), ...$this->personRules('', $inventory)],
            $this->messages(),
            $this->attributes(),
        ));

        if ($data['condition'] === 'new') {
            $this->ensureInStock($data['stock_id'], (int) $inventory->qty, $inventory, 'stock_id');
        }

        return $data;
    }

    /**
     * Rules for the fields shared by every record on the create form. Items
     * and sites must be active, except that a row being edited may keep the
     * one it has.
     */
    private function sharedRules(?Inventory $inventory = null): array
    {
        return [
            'date' => ['required', 'date'],
            'condition' => ['required', Rule::in(['new', 'used'])],
            'stock_id' => ['required', 'integer',
                Rule::exists(Stock::class, 'id')->where($this->activeOr($inventory?->stock_id))],
            'site_id' => ['required', 'integer',
                Rule::exists(Type::class, 'id')->where('type_group', Type::SITE)->where($this->activeOr($inventory?->site_id))],
        ];
    }

    /** Rules for one person's fields, under a key prefix ('people.*.' on the create form). */
    private function personRules(string $prefix, ?Inventory $inventory = null): array
    {
        return [
            $prefix.'taken_by' => ['required', 'string', 'max:255'],
            $prefix.'department_id' => ['required', 'integer',
                Rule::exists(Type::class, 'id')->where('type_group', Type::DEPARTMENT)->where($this->activeOr($inventory?->department_id))],
            $prefix.'serial_number' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function messages(): array
    {
        return [
            'stock_id.exists' => 'The chosen item is no longer in the stock list.',
            'department_id.exists' => 'The chosen department is no longer available.',
            'site_id.exists' => 'The chosen site is no longer available.',
        ];
    }

    private function attributes(): array
    {
        return [
            'stock_id' => 'item',
            'department_id' => 'department',
            'site_id' => 'site',
        ];
    }

    /** Foreign keys arrive as strings; store and compare them as integers. */
    private function castIds(array $data): array
    {
        foreach (['stock_id', 'department_id', 'site_id'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = (int) $data[$field];
            }
        }

        return $data;
    }

    /** Refuse a request for more of an item than is on the shelf (plus what the row already holds). */
    private function ensureInStock(int $stockId, int $requested, ?Inventory $inventory, string $errorKey): void
    {
        $available = $this->ledger->available($stockId, $inventory);

        if ($requested > $available) {
            $item = (string) Stock::whereKey($stockId)->value('item');

            throw ValidationException::withMessages([
                $errorKey => $this->ledger->shortfallMessage($item, $available, $requested),
            ]);
        }
    }

    /** An exists-rule condition: the row is active, or is the one the record already holds. */
    private function activeOr(mixed $keepId): Closure
    {
        return fn ($query) => $query->where('active_yn', true)
            ->when($keepId, fn ($query) => $query->orWhere('id', $keepId));
    }
}

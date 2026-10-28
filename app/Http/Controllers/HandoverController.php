<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryUpdate;
use App\Models\Type;
use App\Services\StockLedger;
use App\Support\InventoryChangeLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Handing an item (one inventory record, one barcode label) to someone else:
 * first Return it to stock, then Assign it to the new person on the same
 * record. Both write an entry on the item's timeline.
 */
class HandoverController extends Controller
{
    /** The fields Assign changes; also those compared for a non-admin's change log. */
    private const FIELDS = ['date', 'taken_by', 'department_id', 'site_id'];

    public function __construct(private StockLedger $ledger) {}

    /**
     * Back from its holder onto the shelf. Any signed-in user may do this
     * (it is on the timeline and in the audit trail); a new item's unit goes
     * back into stock.
     */
    public function return(Request $request, Inventory $inventory)
    {
        if (! $inventory->active_yn || $inventory->isPending() || $inventory->isReturned()) {
            return back()->with('error', match (true) {
                ! $inventory->active_yn => 'This inventory record has been deactivated.',
                $inventory->isPending() => 'This record is awaiting approval, so it cannot be returned yet.',
                default => 'This item has already been returned to stock.',
            });
        }

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $inventory->load(['department', 'site']);
        $user = $request->user();

        DB::transaction(function () use ($inventory, $data, $user) {
            $inventory->returned_at = now();
            $inventory->returned_by = $user->id;

            // A returned row holds nothing, so its new unit goes back on the shelf.
            $this->ledger->save($inventory);

            InventoryUpdate::record($inventory, InventoryUpdate::RETURNED, $this->withNote(
                "Returned to stock by {$user->name}. Previously held by {$this->holder($inventory)}.",
                $data['reason'] ?? null,
                'Reason',
            ));
        });

        return redirect()->route('inventory.show', $inventory)
            ->with('status', "Returned to stock. Assign it to a new person when it's handed over.");
    }

    /**
     * Record the new holder of a returned item on the same record (same
     * barcode). An admin's handover is approved at once and takes the unit
     * from stock again; anyone else's goes to the approval queue as an edit,
     * and stock is taken when an administrator approves it.
     */
    public function assign(Request $request, Inventory $inventory)
    {
        if (! $inventory->active_yn || $inventory->isPending() || ! $inventory->isReturned()) {
            return back()->with('error', match (true) {
                ! $inventory->active_yn => 'This inventory record has been deactivated.',
                $inventory->isPending() => 'This record is awaiting approval, so it cannot be assigned yet.',
                default => 'Return this item to stock before assigning it to someone else.',
            });
        }

        $data = $request->validate([
            'date' => ['required', 'date'],
            'taken_by' => ['required', 'string', 'max:255'],
            'department_id' => ['required', 'integer',
                Rule::exists(Type::class, 'id')->where('type_group', Type::DEPARTMENT)->where('active_yn', true)],
            'site_id' => ['required', 'integer',
                Rule::exists(Type::class, 'id')->where('type_group', Type::SITE)->where('active_yn', true)],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'department_id.exists' => 'The chosen department is no longer available.',
            'site_id.exists' => 'The chosen site is no longer available.',
        ], [
            'taken_by' => 'taken by',
            'department_id' => 'department',
            'site_id' => 'site',
        ]);

        $fields = ['department_id' => (int) $data['department_id'], 'site_id' => (int) $data['site_id']]
            + array_intersect_key($data, array_flip(self::FIELDS));
        $user = $request->user();
        $isAdmin = $user->isAdmin();

        $before = $inventory->only(self::FIELDS);
        $inventory->fill($fields);
        $inventory->returned_at = null;
        $inventory->returned_by = null;

        if (! $isAdmin) {
            // The same route as a non-admin's edit: back to pending, with the
            // changed fields (as names) for the approvals page.
            $changes = InventoryChangeLog::diff(self::FIELDS, $before, $inventory->only(self::FIELDS));
            $inventory->change_log = InventoryChangeLog::merge($inventory->change_log ?? [], $changes) ?: null;
            $inventory->approval_type = 'edit';
            $inventory->status = 'pending';
            $inventory->edited_by = $user->id;
        }

        $inventory->load(['department', 'site']);
        $body = $this->withNote(
            "Assigned to {$this->holder($inventory)} on ".Carbon::parse($fields['date'])->format('d M Y').'.'
                .($isAdmin ? '' : ' Awaiting approval.'),
            $data['notes'] ?? null,
            'Notes',
        );

        try {
            DB::transaction(function () use ($inventory, $body) {
                // An admin's approved new item takes its unit from the shelf
                // again; a pending one takes it when approved.
                $this->ledger->save($inventory);

                InventoryUpdate::record($inventory, InventoryUpdate::ASSIGNED, $body);
            });
        } catch (ValidationException $e) {
            // Shown against the item, as the edit form does.
            throw ValidationException::withMessages(['stock_id' => $e->validator->errors()->first('qty')]);
        }

        return redirect()->route('inventory.show', $inventory)->with('status', $isAdmin
            ? "Assigned to {$inventory->taken_by}."
            : "Assigned to {$inventory->taken_by} and sent to an administrator for approval. Stock is taken once it is approved.");
    }

    /** "Sam Example (Finance, Dry Goods)": who holds the item, and where. */
    private function holder(Inventory $inventory): string
    {
        $where = array_filter([$inventory->department?->type_name, $inventory->site?->type_name]);

        return $inventory->taken_by.($where ? ' ('.implode(', ', $where).')' : '');
    }

    /** The timeline sentence, with what the user typed (if anything) after it. */
    private function withNote(string $sentence, ?string $note, string $label): string
    {
        $note = trim((string) $note);

        return $note === '' ? $sentence : "{$sentence} {$label}: {$note}";
    }
}

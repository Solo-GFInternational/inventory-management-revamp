<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Keeps `stocks.qty` in step with the inventory rows that have been issued.
 *
 * The invariant: stocks.qty is what is on the shelf now, and stock has been
 * deducted for exactly the inventory rows that are active, approved, in
 * 'new' condition and not returned (returned_at is null: a returned item is
 * back on the shelf until it is assigned to someone else). So for any stock
 * item, "given out" is SUM(qty) of those rows and the total is in stock +
 * given out.
 *
 * Every change to an inventory row goes through save(): it compares what the
 * row held from stock before the change with what it holds after, moves the
 * difference in or out of stock, and saves the row — all in one transaction,
 * so a shortfall leaves both the row and the stock untouched.
 */
class StockLedger
{
    /**
     * Save the inventory row and move stock for whatever its change means.
     *
     * @throws ValidationException (on `qty`) when there isn't enough stock
     */
    public function save(Inventory $inventory): void
    {
        DB::transaction(function () use ($inventory) {
            $before = $inventory->exists ? $this->holding($inventory->getOriginal()) : null;
            $after = $this->holding($inventory->getAttributes() + ['active_yn' => true]);

            $this->move($before, $after);

            $inventory->save();
        });
    }

    /**
     * How many of a stock item can be issued now: the active stock row's
     * count, plus whatever the given inventory row already holds of that item
     * (it goes back on the shelf before the row takes its new quantity).
     */
    public function available(int $stockId, ?Inventory $inventory = null): int
    {
        $onShelf = (int) Stock::active()->whereKey($stockId)->value('qty');

        $held = $inventory?->exists ? $this->holding($inventory->getAttributes()) : null;

        return $onShelf + ($held && $held['stock_id'] === $stockId ? $held['qty'] : 0);
    }

    /**
     * The message shown when a request asks for more than is available.
     */
    public function shortfallMessage(string $item, int $available, int $requested): string
    {
        if ($available <= 0) {
            return "There is no {$item} in stock.";
        }

        return "Only {$available} {$item} available in stock — you asked for {$requested}.";
    }

    /**
     * What a row with these attributes has taken from stock:
     * ['stock_id', 'qty'], or null when it holds nothing (inactive, pending,
     * used condition, or returned to stock).
     */
    private function holding(array $attributes): ?array
    {
        $active = filter_var($attributes['active_yn'] ?? true, FILTER_VALIDATE_BOOLEAN);

        if (! $active || ($attributes['status'] ?? null) !== 'approved' || ($attributes['condition'] ?? null) !== 'new'
            || ($attributes['returned_at'] ?? null) !== null) {
            return null;
        }

        return ['stock_id' => (int) $attributes['stock_id'], 'qty' => (int) $attributes['qty']];
    }

    /**
     * Put back what the row held before and take what it holds after,
     * netted per stock item, so an unchanged row touches nothing.
     */
    private function move(?array $before, ?array $after): void
    {
        $net = [];

        if ($before) {
            $net[$before['stock_id']] = ($net[$before['stock_id']] ?? 0) + $before['qty'];
        }

        if ($after) {
            $net[$after['stock_id']] = ($net[$after['stock_id']] ?? 0) - $after['qty'];
        }

        foreach ($net as $stockId => $delta) {
            if ($delta > 0) {
                $this->restore($stockId, $delta);
            } elseif ($delta < 0) {
                $this->deduct($stockId, -$delta, $after['qty'] ?? -$delta);
            }
        }
    }

    /** Put stock back on the shelf, even if the item has since been deactivated. */
    private function restore(int $stockId, int $qty): void
    {
        $stock = Stock::whereKey($stockId)->lockForUpdate()->firstOrFail();

        $stock->qty = (int) $stock->qty + $qty;
        $stock->save();
    }

    private function deduct(int $stockId, int $qty, int $requested): void
    {
        $stock = Stock::whereKey($stockId)->lockForUpdate()->first();
        $onShelf = $stock?->active_yn ? (int) $stock->qty : 0;

        if ($onShelf < $qty) {
            // Report it the way the form does: what this row could have had.
            $available = $onShelf + ($requested - $qty);

            throw ValidationException::withMessages([
                'qty' => $this->shortfallMessage($stock?->item ?? 'such item', $available, $requested),
            ]);
        }

        $stock->qty = $onShelf - $qty;
        $stock->save();
    }
}

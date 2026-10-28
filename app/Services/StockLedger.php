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
 * deducted for exactly the inventory rows that are active, approved and in
 * 'new' condition. So for any item, "given out" is SUM(qty) of those rows and
 * the total is in stock + given out.
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
     * How many of an item can be issued now: the active stock row's count,
     * plus whatever the given inventory row already holds of that item (it
     * goes back on the shelf before the row takes its new quantity).
     */
    public function available(string $item, ?Inventory $inventory = null): int
    {
        $onShelf = (int) Stock::active()->where('item', $item)->value('qty');

        $held = $inventory?->exists ? $this->holding($inventory->getAttributes()) : null;

        return $onShelf + ($held && $held['item'] === $item ? $held['qty'] : 0);
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
     * What a row with these attributes has taken from stock: ['item', 'qty'],
     * or null when it holds nothing (inactive, pending, or used condition).
     */
    private function holding(array $attributes): ?array
    {
        $active = filter_var($attributes['active_yn'] ?? true, FILTER_VALIDATE_BOOLEAN);

        if (! $active || ($attributes['status'] ?? null) !== 'approved' || ($attributes['condition'] ?? null) !== 'new') {
            return null;
        }

        return ['item' => (string) $attributes['item'], 'qty' => (int) $attributes['qty']];
    }

    /**
     * Put back what the row held before and take what it holds after,
     * netted per item, so an unchanged row touches nothing.
     */
    private function move(?array $before, ?array $after): void
    {
        $net = [];

        if ($before) {
            $net[$before['item']] = ($net[$before['item']] ?? 0) + $before['qty'];
        }

        if ($after) {
            $net[$after['item']] = ($net[$after['item']] ?? 0) - $after['qty'];
        }

        foreach ($net as $item => $delta) {
            if ($delta > 0) {
                $this->restore($item, $delta);
            } elseif ($delta < 0) {
                $this->deduct($item, -$delta, $after['qty'] ?? -$delta);
            }
        }
    }

    private function restore(string $item, int $qty): void
    {
        $stock = Stock::where('item', $item)->lockForUpdate()->first();

        if (! $stock) {
            Stock::create(['item' => $item, 'qty' => $qty]);

            return;
        }

        $stock->qty = (int) $stock->qty + $qty;
        $stock->save();
    }

    private function deduct(string $item, int $qty, int $requested): void
    {
        $stock = Stock::active()->where('item', $item)->lockForUpdate()->first();
        $onShelf = $stock ? (int) $stock->qty : 0;

        if ($onShelf < $qty) {
            // Report it the way the form does: what this row could have had.
            $available = $onShelf + ($requested - $qty);

            throw ValidationException::withMessages([
                'qty' => $this->shortfallMessage($item, $available, $requested),
            ]);
        }

        $stock->qty = $onShelf - $qty;
        $stock->save();
    }
}

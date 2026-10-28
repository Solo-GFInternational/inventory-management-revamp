<?php

namespace Tests\Concerns;

use App\Models\Inventory;
use App\Models\Stock;
use App\Models\Type;
use App\Models\User;

/**
 * Builds users, stock, types and inventory rows for feature tests. Tests can
 * name the item, department and site ('item' => 'Monitor', 'site' => ...);
 * withIds() turns those names into stock_id / department_id / site_id,
 * creating the stock row (qty 0) or type on first use.
 */
trait CreatesInventory
{
    protected function makeUser(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->forceFill(['role' => $role])->save();

        return $user->fresh();
    }

    /** The active stock row for an item, created with qty 0 if there is none. */
    protected function stockFor(string $item): Stock
    {
        return Stock::active()->where('item', $item)->first()
            ?? Stock::create(['item' => $item, 'qty' => 0]);
    }

    /** The ref.types row of a group with this name, created if there is none. */
    protected function typeFor(string $group, string $name): Type
    {
        return Type::firstOrCreate(['type_group' => $group, 'type_name' => $name]);
    }

    /**
     * Replace 'item', 'department' and 'site' names with the matching ids.
     */
    protected function withIds(array $attributes): array
    {
        // An explicit id wins over a name (e.g. a default name in makeInventory).
        if (array_key_exists('item', $attributes)) {
            $attributes['stock_id'] ??= $this->stockFor($attributes['item'])->id;
            unset($attributes['item']);
        }

        foreach (['department' => Type::DEPARTMENT, 'site' => Type::SITE] as $key => $group) {
            if (array_key_exists($key, $attributes)) {
                $attributes[$key.'_id'] ??= $this->typeFor($group, $attributes[$key])->id;
                unset($attributes[$key]);
            }
        }

        return $attributes;
    }

    /**
     * An inventory row saved directly (no StockLedger, so stock is untouched).
     * Defaults: 1 × Laptop Bag, new, approved, Finance at Dry Goods.
     */
    protected function makeInventory(User $givenBy, array $overrides = []): Inventory
    {
        $inventory = new Inventory;
        $inventory->forceFill($this->withIds($overrides + [
            'date' => now()->toDateString(),
            'qty' => 1,
            'item' => 'Laptop Bag',
            'condition' => 'new',
            'given_by' => $givenBy->id,
            'taken_by' => 'Someone',
            'department' => 'Finance',
            'site' => 'Dry Goods',
            'status' => 'approved',
        ]))->save();

        return $inventory;
    }
}

<?php

namespace Tests\Concerns;

use App\Models\Inventory;
use App\Models\Stock;
use App\Models\Type;
use App\Models\User;

/**
 * Rows for the Settings / audit trail tests: ref.types options, and
 * inventory rows built on the new columns (stock_id, department_id,
 * site_id), creating the stock item and types they point at by name.
 */
trait CreatesTypes
{
    protected function makeType(string $group, string $name, int $sortOrder = 0, bool $active = true): Type
    {
        $type = new Type(['type_group' => $group, 'type_name' => $name, 'sort_order' => $sortOrder]);
        $type->active_yn = $active;
        $type->save();

        return $type;
    }

    /** The active type of that group and name, created if missing. */
    protected function typeNamed(string $group, string $name): Type
    {
        return Type::query()->group($group)->where('type_name', $name)->where('active_yn', true)->first()
            ?? $this->makeType($group, $name);
    }

    /** The active stock item of that name, created (with 10 on the shelf) if missing. */
    protected function stockNamed(string $item, int $qty = 10): Stock
    {
        return Stock::query()->where('item', $item)->where('active_yn', true)->first()
            ?? Stock::create(['item' => $item, 'qty' => $qty]);
    }

    /**
     * An inventory row saved straight to the table (no controller, so no
     * stock movement). Item, department and site are given by name in the
     * 'item' / 'department' / 'site' keys; any other key is a column.
     */
    protected function makeInventoryRow(User $givenBy, array $overrides = []): Inventory
    {
        $item = $overrides['item'] ?? 'Laptop Bag';
        $department = $overrides['department'] ?? 'Finance';
        $site = $overrides['site'] ?? 'Dry Goods';
        unset($overrides['item'], $overrides['department'], $overrides['site']);

        $inventory = new Inventory;
        $inventory->forceFill($overrides + [
            'date' => now()->toDateString(),
            'qty' => 1,
            'stock_id' => $this->stockNamed($item)->id,
            'condition' => 'new',
            'given_by' => $givenBy->id,
            'taken_by' => 'Jane',
            'department_id' => $this->typeNamed(Type::DEPARTMENT, $department)->id,
            'site_id' => $this->typeNamed(Type::SITE, $site)->id,
            'status' => 'approved',
        ])->save();

        return $inventory;
    }
}

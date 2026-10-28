<?php

namespace App\Support;

use App\Models\Stock;
use App\Models\Type;

/**
 * The field diff a non-admin's change records in an inventory row's
 * `change_log` for the approvals page. Shared by the edit form
 * (InventoryController) and Assign to new person (HandoverController).
 */
class InventoryChangeLog
{
    /**
     * Foreign-key fields and the key they are stored under in change_log. The
     * change log records names (item, department, site), not ids, so the
     * approvals page shows what the user saw when they made the edit.
     */
    private const LOG_KEYS = ['stock_id' => 'item', 'department_id' => 'department', 'site_id' => 'site'];

    /**
     * The changed fields between two sets of values, keyed as change_log
     * stores them: [key => ['old' => name, 'new' => name]].
     *
     * @param  list<string>  $fields
     */
    public static function diff(array $fields, array $before, array $after): array
    {
        $changes = [];

        foreach ($fields as $field) {
            $old = self::normalise($field, $before[$field] ?? null);
            $new = self::normalise($field, $after[$field] ?? null);

            if ($old !== $new) {
                $changes[self::LOG_KEYS[$field] ?? $field] = [
                    'old' => self::displayValue($field, $old),
                    'new' => self::displayValue($field, $new),
                ];
            }
        }

        return $changes;
    }

    /** Fold a new diff into an existing one, keeping each field's original value. */
    public static function merge(array $existing, array $changes): array
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

    /** The name a foreign-key value is shown and logged as (other fields as they are). */
    private static function displayValue(string $field, ?string $value): ?string
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
    private static function normalise(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($field === 'date') {
            return substr((string) $value, 0, 10);
        }

        return (string) $value;
    }
}

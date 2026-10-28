<?php

namespace App\Models;

use App\Models\Concerns\HasStandardColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One entry on an inventory item's timeline: a note someone typed (e.g. a
 * part fitted to a laptop) or a returned / assigned event the handover
 * actions write. Who and when come from the standard columns
 * (created_by / created_at). Never deleted; a removed note is deactivated.
 */
#[Fillable(['inventory_id', 'kind', 'body'])]
class InventoryUpdate extends Model
{
    use HasStandardColumns;

    public const NOTE = 'note';

    public const RETURNED = 'returned';

    public const ASSIGNED = 'assigned';

    protected $table = 'inventory.inventory_updates';

    /** Add an entry to an item's timeline (created_by is filled from Auth). */
    public static function record(Inventory $inventory, string $kind, ?string $body = null): self
    {
        return static::create(['inventory_id' => $inventory->getKey(), 'kind' => $kind, 'body' => $body]);
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

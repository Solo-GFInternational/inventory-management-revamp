<?php

namespace App\Models;

use App\Models\Concerns\HasStandardColumns;
use App\Services\BarcodeLabel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'date',
    'qty',
    'stock_id',
    'serial_number',
    'condition',
    'given_by',
    'taken_by',
    'department_id',
    'status',
    'site_id',
    'edited_by',
    'approval_type',
])]
// `barcode` is system-assigned (INV + padded id) when the row is created, so it isn't fillable.
class Inventory extends Model
{
    use HasFactory, HasStandardColumns;

    protected $table = 'inventory.inventories';

    /**
     * Every record gets its barcode as soon as it has an id, however it was
     * created (controller, seeder, tests). The value is saved with the row's
     * transaction; the label JPEG is written once that commits, so a rolled-back
     * batch leaves no files behind. A failed write is reported, not thrown:
     * the label is regenerated when it is next shown or downloaded.
     */
    protected static function booted(): void
    {
        static::created(function (Inventory $inventory) {
            if ($inventory->barcode) {
                return;
            }

            $inventory->assignBarcode();

            DB::afterCommit(fn () => rescue(fn () => app(BarcodeLabel::class)->generate($inventory)));
        });
    }

    /** Where the barcode label JPEG lives on the local disk. */
    public function barcodePath(): string
    {
        return 'barcodes/'.$this->barcode.'.jpg';
    }

    /**
     * Give the record its barcode value if it has none. Written straight to
     * the row (no model events, so no audit entry, and safe mid-create while
     * the model's original values aren't synced yet).
     */
    public function assignBarcode(): void
    {
        if ($this->barcode) {
            return;
        }

        $barcode = BarcodeLabel::valueFor($this->getKey());

        $this->newQueryWithoutScopes()->whereKey($this->getKey())->toBase()->update(['barcode' => $barcode]);

        $this->barcode = $barcode;
        $this->syncOriginalAttribute('barcode');
    }

    /** The stock item issued (its name is `stock->item`). */
    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }

    /** ref.types row in the 'department' group. */
    public function department()
    {
        return $this->belongsTo(Type::class, 'department_id');
    }

    /** ref.types row in the 'site' group. */
    public function site()
    {
        return $this->belongsTo(Type::class, 'site_id');
    }

    public function giver()
    {
        return $this->belongsTo(User::class, 'given_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    public function given_by_user()
    {
        return $this->belongsTo(User::class, 'given_by');
    }

    /** Who returned the item to stock (set while it is back on the shelf). */
    public function returner()
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    /** The item's timeline: notes and returned / assigned events. */
    public function updates()
    {
        return $this->hasMany(InventoryUpdate::class);
    }

    /** Returned to stock and waiting to be assigned to a new person. */
    public function isReturned(): bool
    {
        return $this->returned_at !== null;
    }

    /** Rows waiting for an administrator to approve them (new or edited). */
    public function scopePending($query)
    {
        return $query->where($this->qualifyColumn('status'), 'pending');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    protected function casts(): array
    {
        return [
            'change_log' => 'array',
            'returned_at' => 'datetime',
        ];
    }
}

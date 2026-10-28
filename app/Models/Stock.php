<?php

namespace App\Models;

use App\Models\Concerns\HasStandardColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable(['item', 'qty'])]
class Stock extends Model
{
    use HasFactory, HasStandardColumns;

    protected $table = 'inventory.stocks';

    /** In stock at or below this count is flagged as low. */
    public const LOW_STOCK = 2;

    /** The inventory rows that issued this item. */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /**
     * Adds a `given_out` column: the quantity of this item issued as new
     * (active, approved inventory rows with condition "new"), from one
     * grouped subquery rather than a query per row.
     */
    public function scopeWithGivenOut(Builder $query): Builder
    {
        $givenOut = Inventory::active()
            ->select('stock_id')
            ->selectRaw('SUM(qty) as given_out')
            ->where('status', 'approved')
            ->where('condition', 'new')
            ->groupBy('stock_id');

        return $query
            ->leftJoinSub($givenOut, 'given', 'given.stock_id', '=', $this->qualifyColumn('id'))
            ->select($this->qualifyColumn('*'))
            ->addSelect(DB::raw('COALESCE(given.given_out, 0) as given_out'));
    }

    /** What's on the shelf now. */
    public function inStock(): int
    {
        return (int) $this->qty;
    }

    /** Issued as new; needs the withGivenOut() scope. */
    public function givenOut(): int
    {
        return (int) ($this->given_out ?? 0);
    }

    /** In stock plus given out. */
    public function total(): int
    {
        return $this->inStock() + $this->givenOut();
    }

    public function isOutOfStock(): bool
    {
        return $this->inStock() <= 0;
    }

    public function isLowStock(): bool
    {
        return ! $this->isOutOfStock() && $this->inStock() <= self::LOW_STOCK;
    }
}

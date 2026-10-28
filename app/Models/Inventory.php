<?php

namespace App\Models;

use App\Models\Concerns\HasStandardColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
class Inventory extends Model
{
    use HasFactory, HasStandardColumns;

    protected $table = 'inventory.inventories';

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
        ];
    }
}

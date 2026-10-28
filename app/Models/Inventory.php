<?php

namespace App\Models;

use App\Models\Concerns\HasStandardColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'date',
    'qty',
    'item',
    'serial_number',
    'condition',
    'given_by',
    'taken_by',
    'department',
    'status',
    'site',
    'edited_by',
    'approval_type',
])]
class Inventory extends Model
{
    use HasFactory, HasStandardColumns;

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

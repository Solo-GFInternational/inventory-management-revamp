<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    protected $fillable = [
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
];

    protected $casts = [
    'change_log' => 'array',
];

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



}

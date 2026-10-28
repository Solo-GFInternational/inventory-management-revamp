<?php

namespace App\Models;

use App\Models\Concerns\HasStandardColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory, HasStandardColumns;
}

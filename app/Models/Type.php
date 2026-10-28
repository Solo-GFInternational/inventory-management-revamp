<?php

namespace App\Models;

use App\Models\Concerns\HasStandardColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A row of ref.types: one option of a managed dropdown list, grouped by
 * type_group (see the GROUPS constant). Managed under Settings > Types;
 * never deleted, only deactivated.
 */
#[Fillable(['type_group', 'type_name', 'sort_order'])]
class Type extends Model
{
    use HasFactory, HasStandardColumns;

    public const DEPARTMENT = 'department';

    public const SITE = 'site';

    /** The groups people can manage, with their display labels. */
    public const GROUPS = [
        self::DEPARTMENT => 'Department',
        self::SITE => 'Site',
    ];

    protected $table = 'ref.types';

    public function scopeGroup(Builder $query, string $group): Builder
    {
        return $query->where($this->qualifyColumn('type_group'), $group);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy($this->qualifyColumn('sort_order'))->orderBy($this->qualifyColumn('type_name'));
    }

    /**
     * Active options of a group for a <select>, id => name. Pass the id a
     * record already holds to keep it listed even if it has been deactivated.
     *
     * @return Collection<int, string>
     */
    public static function options(string $group, ?int $keepId = null): Collection
    {
        return static::query()
            ->group($group)
            ->where(fn (Builder $query) => $query->where('active_yn', true)
                ->when($keepId, fn (Builder $query) => $query->orWhere('id', $keepId)))
            ->ordered()
            ->pluck('type_name', 'id');
    }
}

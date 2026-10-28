<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTypeRequest;
use App\Http\Requests\UpdateTypeRequest;
use App\Models\Inventory;
use App\Models\Type;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Settings > Types: the managed dropdown lists in ref.types (departments and
 * sites, see Type::GROUPS). Create and edit happen in modals on the list
 * page, as on Settings > Users. Types are never deleted: "delete"
 * deactivates (active_yn = false), so inventory rows that already use one
 * keep it while new rows can't pick it; reactivate restores it.
 */
class TypeController extends Controller
{
    public function index(Request $request): View
    {
        $group = array_key_exists((string) $request->query('group'), Type::GROUPS) ? (string) $request->query('group') : '';
        $status = in_array($request->query('status'), ['active', 'inactive', 'all'], true)
            ? $request->query('status')
            : 'active';
        $search = trim((string) $request->query('q'));

        $types = Type::query()
            ->when($group !== '', fn ($query) => $query->group($group))
            ->when($status === 'active', fn ($query) => $query->where('active_yn', true))
            ->when($status === 'inactive', fn ($query) => $query->where('active_yn', false))
            ->when($search !== '', fn ($query) => $query->where('type_name', 'like', '%'.$search.'%'))
            ->orderBy('type_group')
            ->ordered()
            ->get();

        return view('settings.types.index', [
            'types' => $types,
            'groups' => Type::GROUPS,
            'usage' => $this->usage($types),
            'group' => $group,
            'status' => $status,
            'search' => $search,
        ]);
    }

    /**
     * Adds a type. A name that matches a deactivated type in the same group
     * brings that type back (with the order given) rather than adding a
     * second row; an active match is refused by StoreTypeRequest.
     */
    public function store(StoreTypeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $groupLabel = Type::GROUPS[$data['type_group']];

        $type = Type::query()
            ->group($data['type_group'])
            ->where('type_name', $data['type_name'])
            ->where('active_yn', false)
            ->orderByDesc('updated_at')
            ->first();

        if ($type !== null) {
            $type->fill($data);
            $type->active_yn = true;
            $type->save();

            return redirect()->route('types.index', ['group' => $type->type_group])->with([
                'status_title' => 'Type reactivated',
                'status' => "{$type->type_name} had been deactivated; it is back in the {$groupLabel} list.",
            ]);
        }

        $type = Type::create($data);

        return redirect()->route('types.index', ['group' => $type->type_group])->with([
            'status_title' => 'Type created',
            'status' => "{$type->type_name} has been added to the {$groupLabel} list.",
        ]);
    }

    public function update(UpdateTypeRequest $request, Type $type): RedirectResponse
    {
        $type->fill($request->validated());
        $type->save();

        return redirect()->back()->with([
            'status_title' => 'Type updated',
            'status' => "{$type->type_name} has been saved.",
        ]);
    }

    /**
     * Deactivates the type (active_yn = false). Allowed even while inventory
     * uses it: those rows keep it; it is only left out of the dropdown for
     * new ones.
     */
    public function destroy(Type $type): RedirectResponse
    {
        $type->active_yn = false;
        $type->save();

        $groupLabel = Type::GROUPS[$type->type_group] ?? $type->type_group;

        return back()->with([
            'status_title' => 'Type deactivated',
            'status' => "{$type->type_name} will no longer be offered in the {$groupLabel} list. Records that use it keep it.",
        ]);
    }

    public function reactivate(Type $type): RedirectResponse
    {
        // Only one active type per group and name (ux_types_type_group_type_name):
        // one may have been added with this name since it was deactivated.
        $clash = Type::query()
            ->group($type->type_group)
            ->where('type_name', $type->type_name)
            ->where('active_yn', true)
            ->whereKeyNot($type->getKey())
            ->exists();

        if ($clash) {
            return back()->with('error', "An active type named {$type->type_name} is already in this group. Rename one of them first.");
        }

        $type->active_yn = true;
        $type->save();

        $groupLabel = Type::GROUPS[$type->type_group] ?? $type->type_group;

        return back()->with([
            'status_title' => 'Type reactivated',
            'status' => "{$type->type_name} is back in the {$groupLabel} list.",
        ]);
    }

    /**
     * How many active inventory rows use each listed type (as their
     * department or site), type id => count, in two grouped queries.
     *
     * @param  Collection<int, Type>  $types
     * @return array<int|string, int>
     */
    private function usage(Collection $types): array
    {
        if ($types->isEmpty()) {
            return [];
        }

        $ids = $types->modelKeys();
        $usage = [];

        foreach (['department_id', 'site_id'] as $column) {
            Inventory::query()
                ->where('active_yn', true)
                ->whereIn($column, $ids)
                ->groupBy($column)
                ->selectRaw("{$column} as type_id, COUNT(*) as uses")
                ->toBase()
                ->get()
                ->each(function ($row) use (&$usage) {
                    $usage[$row->type_id] = ($usage[$row->type_id] ?? 0) + (int) $row->uses;
                });
        }

        return $usage;
    }
}

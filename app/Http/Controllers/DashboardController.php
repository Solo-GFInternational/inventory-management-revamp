<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Stock;
use App\Models\Type;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $site = $request->integer('site_id') ?: null;
        $department = $request->integer('department_id') ?: null;

        // Stat cards. Deactivated rows never count; "given out" and "issued"
        // only count approved issues (pending ones haven't left the shelf).
        $inStock = (int) Stock::active()->sum('qty');

        $givenOut = (int) Inventory::active()
            ->where('status', 'approved')
            ->where('condition', 'new')
            ->sum('qty');

        $pendingCount = Inventory::active()->where('status', 'pending')->count();

        $issuedThisMonth = (int) Inventory::active()
            ->where('status', 'approved')
            ->whereBetween('date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('qty');

        // Item-wise total quantity issued (approved), filterable by site and
        // department; one row per stock item, named from inventory.stocks.
        $inventories = (new Inventory)->getTable();
        $stocks = (new Stock)->getTable();

        $items = Inventory::active()
            ->join($stocks, "{$stocks}.id", '=', "{$inventories}.stock_id")
            ->where("{$inventories}.status", 'approved')
            ->when($site, fn ($query) => $query->where("{$inventories}.site_id", $site))
            ->when($department, fn ($query) => $query->where("{$inventories}.department_id", $department))
            ->select("{$inventories}.stock_id", "{$stocks}.item", DB::raw("SUM({$inventories}.qty) as total_qty"))
            ->groupBy("{$inventories}.stock_id", "{$stocks}.item")
            ->orderBy("{$stocks}.item")
            ->get();

        // Filter dropdowns: the managed lists, keeping a chosen value that has
        // since been deactivated.
        $sites = Type::options(Type::SITE, $site);
        $departments = Type::options(Type::DEPARTMENT, $department);

        $recent = Inventory::active()
            ->with(['stock', 'department', 'site'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return view('dashboard', compact(
            'inStock', 'givenOut', 'pendingCount', 'issuedThisMonth',
            'items', 'sites', 'departments', 'site', 'department', 'recent',
        ));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $site = $request->string('site')->trim()->toString() ?: null;
        $department = $request->string('department')->trim()->toString() ?: null;

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

        // Item-wise total quantity issued (approved), filterable by site and department.
        $items = Inventory::active()
            ->where('status', 'approved')
            ->when($site, fn ($query) => $query->where('site', $site))
            ->when($department, fn ($query) => $query->where('department', $department))
            ->select('item', DB::raw('SUM(qty) as total_qty'))
            ->groupBy('item')
            ->orderBy('item')
            ->get();

        // Filter dropdowns, from active rows only.
        $sites = Inventory::active()->whereNotNull('site')->where('site', '<>', '')
            ->distinct()->orderBy('site')->pluck('site');
        $departments = Inventory::active()->whereNotNull('department')->where('department', '<>', '')
            ->distinct()->orderBy('department')->pluck('department');

        $recent = Inventory::active()
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

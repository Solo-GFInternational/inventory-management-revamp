<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Inventory;
use DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $site = $request->site;
        $department = $request->department;

        $query = Inventory::query();

        if ($site) {
            $query->where('site', $site);
        }

        if ($department) {
            $query->where('department', $department);
        }

        // Item-wise total quantity
        $items = $query
            ->select('item', DB::raw('SUM(qty) as total_qty'))
            ->groupBy('item')
            ->orderBy('item')
            ->get();

        // Dropdown data
        $sites = Inventory::select('site')->distinct()->orderBy('site')->pluck('site');
        $departments = Inventory::select('department')->distinct()->orderBy('department')->pluck('department');

        return view('dashboard', compact('items', 'sites', 'departments', 'site', 'department'));
    }
}

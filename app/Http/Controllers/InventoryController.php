<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\Inventory;
use Illuminate\Http\Request;
use App\Models\Item;
use App\Models\Stock;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;


class InventoryController extends Controller
{
    
public function index(Request $request)
{
    $query = Inventory::query();

    // 🔍 Search by Taken By
    if ($request->filled('taken_by')) {
        $query->where('taken_by', 'like', '%' . $request->taken_by . '%');
    }

    // 🔍 Search by Site
    if ($request->filled('site')) {
        $query->where('site', $request->site);
    }

    $inventories = $query->latest()->get();

    return view('inventory.index', compact('inventories'));
}


    public function create()
    {
        return view('inventory.create');
    }

    public function store(Request $request)
{
    $request->validate([
        'date' => 'required|date',
        'qty' => 'required|integer',
        'item' => 'required|string',
        'serial_number' => 'nullable|string',
        'condition' => 'required|in:new,used',
        'taken_by' => 'required|string',
        'department' => 'required|string',
        'site' => 'required|string',
    ]);

    $status = auth()->user()->isAdmin() ? 'approved' : 'pending';

    Inventory::create([
        'date' => $request->date,
        'qty' => $request->qty,
        'item' => $request->item,
        'serial_number' => $request->serial_number,
        'condition' => $request->condition,
        'site' => $request->site,
        'department' => $request->department,
        'taken_by' => $request->taken_by,
        'given_by' => auth()->id(),  // <-- Make sure this is set
        'status' => $status,
        'approval_type' => auth()->user()->isAdmin() ? null : 'new',
    ]);

    return redirect()->route('inventory.index')
        ->with('success', 'Inventory submitted successfully and pending approval');
}


    public function edit(Inventory $inventory)
    {
        return view('inventory.edit', compact('inventory'));
    }

    public function update(Request $request, Inventory $inventory)
    {
        $request->validate([
            'date' => 'required|date',
            'qty' => 'required|integer',
            'item' => 'required|string',
            'serial_number' => 'required|string',
            'condition' => 'required',
            'taken_by' => 'required|string',
            'department' => 'required|string',
            'site' => 'required|string', // <-- Add this
        ]);

        // 1️⃣ Capture OLD values
        $oldData = $inventory->only([
            'date',
            'qty',
            'item',
            'serial_number',
            'condition',
            'taken_by',
            'department',
            'site',
        ]);

        // 2️⃣ Update inventory
        $inventory->update([
            'date' => $request->date,
    'qty' => $request->qty,
    'item' => $request->item,
    'serial_number' => $request->serial_number,
    'condition' => $request->condition,
    'taken_by' => $request->taken_by,
    'department' => $request->department,
    'site' => $request->site, // ✅ update site
        ]);

        // 3️⃣ Detect changes
        $changes = [];

        foreach ($oldData as $field => $oldValue) {
            if ($oldValue != $inventory->$field) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $inventory->$field,
                ];
            }
        }

        // 4️⃣ User edit → approval required
        if (auth()->user()->isUser() && count($changes) > 0) {
            $inventory->status = 'pending';
            $inventory->approval_type = 'edit';
            $inventory->change_log = $changes;
            $inventory->edited_by = auth()->id();
            $inventory->save();
        }

        // 5️⃣ Audit log
        Audit::create([
            'user_id' => auth()->id(),
            'inventory_id' => $inventory->id,
            'action' => auth()->user()->isAdmin()
                ? 'edited'
                : 'edited (approval pending)',
            'changes' => json_encode($changes),
        ]);

        return redirect()
            ->route('inventory.index')
            ->with('success', 'Inventory updated successfully');
    }

    public function pending()
    {
        $inventories = Inventory::where('status', 'pending')->get();
        return view('inventory.pending', compact('inventories'));
    }

    public function approve(Inventory $inventory)
    {
         if ($inventory->condition === 'new' && $inventory->status === 'pending') {

        $stock = Stock::where('item', $inventory->item)->first();

        if (!$stock || $stock->qty < $inventory->qty) {
            return back()->with('error', 'Not enough stock available to approve this inventory.');
        }

        $stock->decrement('qty', $inventory->qty);
    }

    // Update inventory status
    $inventory->update([
        'status' => 'approved',
        'approval_type' => null,
    ]);

        $inventory->status = 'approved';
        $inventory->approval_type = null;
        $inventory->change_log = null;
        $inventory->save();

        return back()->with('success', 'Inventory approved successfully and stock updated.');
    }

    

    public function destroy(Inventory $inventory)
    {

        
        Audit::create([
            'user_id' => auth()->id(),
            'inventory_id' => $inventory->id,
            'action' => 'deleted',
            'changes' => json_encode($inventory->toArray()),
        ]);

        $inventory->delete();

        return redirect()
            ->route('inventory.index')
            ->with('success', 'Inventory deleted successfully');
    }

    


public function downloadPdf(Request $request)
{
    $year = $request->year ?? now()->year;

    $inventories = Inventory::with('editor')
        ->whereYear('date', $year)   // 👈 filter by year
        ->orderBy('date')
        ->get();

    return Pdf::loadView('inventory.pdf', compact('inventories', 'year'))
        ->setPaper('A4', 'landscape')
        ->download("inventory_{$year}.pdf");
}


    

    
    
}

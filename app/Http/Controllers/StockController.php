<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function index()
    {
        $stocks = Stock::orderBy('item')->get();
        return view('stock.index', compact('stocks'));
    }

    public function create()
    {
        return view('stock.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'item' => 'required|string',
            'qty' => 'required|integer|min:1',
        ]);

        $stock = Stock::firstOrCreate(
            ['item' => $request->item],
            ['qty' => 0]
        );

        $stock->increment('qty', $request->qty);

        return redirect()->route('stock.index')
            ->with('success', 'Stock added successfully');
    }
}

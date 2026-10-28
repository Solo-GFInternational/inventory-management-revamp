<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        // The stock list is small, so load every active item once: the stat
        // cards total all of them, and the table shows the search matches.
        $allStocks = Stock::active()->withGivenOut()->orderBy('stocks.item')->get();

        $totals = [
            'inStock' => $allStocks->sum(fn (Stock $stock) => $stock->inStock()),
            'givenOut' => $allStocks->sum(fn (Stock $stock) => $stock->givenOut()),
            'total' => $allStocks->sum(fn (Stock $stock) => $stock->total()),
        ];

        $stocks = $search === ''
            ? $allStocks
            : $allStocks->filter(fn (Stock $stock) => Str::contains($stock->item, $search, ignoreCase: true))->values();

        $itemNames = $allStocks->pluck('item');

        return view('stock.index', compact('stocks', 'totals', 'search', 'itemNames'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'item' => [
                'nullable',
                'required_without:new_item',
                'prohibits:new_item',
                'string',
                Rule::exists('stocks', 'item')->where('active_yn', true),
            ],
            'new_item' => ['nullable', 'required_without:item', 'string', 'max:255'],
            'qty' => ['required', 'integer', 'min:1', 'max:100000'],
        ], [
            'item.required_without' => 'Choose an existing item or type a new item name.',
            'new_item.required_without' => 'Choose an existing item or type a new item name.',
            'item.prohibits' => 'Choose an existing item or type a new item name, not both.',
            'item.exists' => 'The chosen item is no longer in the stock list.',
            'qty.required' => 'Enter how many to add.',
            'qty.integer' => 'The quantity must be a whole number.',
            'qty.min' => 'The quantity must be at least 1.',
        ]);

        $name = trim($validated['item'] ?? $validated['new_item']);

        // A typed name that matches an existing (or deactivated) item adds to
        // that item rather than creating a duplicate; stocks.item is unique.
        $stock = Stock::firstOrCreate(['item' => $name], ['qty' => 0]);

        // Atomic increment; reactivates the item if it had been deactivated.
        $stock->increment('qty', (int) $validated['qty'], ['active_yn' => true]);

        return redirect()->route('stock.index')
            ->with('status', "Added {$validated['qty']} × {$stock->item}. {$stock->qty} now in stock.");
    }

    public function update(Request $request, Stock $stock)
    {
        abort_unless($stock->active_yn, 404);

        $validated = $request->validate([
            'qty' => ['required', 'integer', 'min:0', 'max:100000'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'qty.required' => 'Enter the correct in-stock count.',
            'qty.integer' => 'The in-stock count must be a whole number.',
            'qty.min' => 'The in-stock count cannot be negative.',
        ]);

        $old = $stock->inStock();
        $stock->qty = (int) $validated['qty'];
        $stock->save();

        $message = "{$stock->item}: in stock corrected from {$old} to {$stock->qty}.";
        if (! empty($validated['note'])) {
            $message .= " Note: {$validated['note']}";
        }

        return redirect()->route('stock.index')->with('status', $message);
    }
}

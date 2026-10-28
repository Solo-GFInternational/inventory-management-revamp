<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The barcode scanner page. The camera is read and decoded in the browser
 * (resources/js/scanner.js, ZXing); the decoded value, or one typed into the
 * manual-entry form, comes back to lookup() as ?code=.
 */
class ScannerController extends Controller
{
    public function index()
    {
        return view('scanner.index');
    }

    public function lookup(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        // Barcodes are stored upper-case (INV000123), so a typed "inv000123" still matches.
        $code = Str::upper(trim($validated['code']));

        // Deactivated records are found too: the details page badges them.
        $inventory = Inventory::where('barcode', $code)->first();

        if (! $inventory) {
            return redirect()->route('scanner.index')
                ->with('error', "No inventory record has barcode {$code}.")
                ->withInput(['code' => $code]);
        }

        return redirect()->route('inventory.show', $inventory);
    }
}

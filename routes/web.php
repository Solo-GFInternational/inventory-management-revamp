<?php

use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\TypeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// Any signed-in user
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Create and edit happen in modals on the list page. Non-admin
    // additions and edits go into the approval queue.
    Route::get('/inventory/download-pdf', [InventoryController::class, 'downloadPdf'])->name('inventory.download.pdf');
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
    Route::put('/inventory/{inventory}', [InventoryController::class, 'update'])->name('inventory.update');

    Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
    Route::post('/stock', [StockController::class, 'store'])->name('stock.store');
});

// Admin only
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/inventory/pending', [InventoryController::class, 'pending'])->name('inventory.pending');
    // The inventory list and the approvals page both post here.
    Route::match(['post', 'patch'], '/inventory/{inventory}/approve', [InventoryController::class, 'approve'])->name('inventory.approve');
    // Deactivates (active_yn = false) rather than deleting the row.
    Route::delete('/inventory/{inventory}', [InventoryController::class, 'destroy'])->name('inventory.destroy');

    // Correcting a stock count.
    Route::put('/stock/{stock}', [StockController::class, 'update'])->name('stock.update');

    // Settings: Users and Audit Trail tabs. Route names are stable; only the URIs live under /settings.
    Route::redirect('/settings', '/settings/users')->name('settings');
    Route::get('/settings/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/settings/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/settings/users/{user}', [UserController::class, 'update'])->name('users.update');
    // Deactivates the account; reactivate restores it.
    Route::delete('/settings/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::patch('/settings/users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');
    Route::get('/settings/audit-trail', [AuditTrailController::class, 'index'])->name('audit.index');
    // Managed dropdown lists (ref.types). Delete deactivates; reactivate restores.
    Route::get('/settings/types', [TypeController::class, 'index'])->name('types.index');
    Route::post('/settings/types', [TypeController::class, 'store'])->name('types.store');
    Route::put('/settings/types/{type}', [TypeController::class, 'update'])->name('types.update');
    Route::delete('/settings/types/{type}', [TypeController::class, 'destroy'])->name('types.destroy');
    Route::patch('/settings/types/{type}/reactivate', [TypeController::class, 'reactivate'])->name('types.reactivate');
});

require __DIR__.'/auth.php';

<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\DashboardController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');

Route::get('/admin-only', function () {
    return 'Admin Area';
})->middleware(['auth', 'role:admin']);

Route::get('/user-only', function () {
    return 'User Area';
})->middleware(['auth', 'role:user']);

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    // View inventory
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');

    // Add inventory
    Route::get('/inventory/create', [InventoryController::class, 'create'])->name('inventory.create');
    Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');

    // Edit inventory
    Route::get('/inventory/{inventory}/edit', [InventoryController::class, 'edit'])->name('inventory.edit');
    Route::put('/inventory/{inventory}', [InventoryController::class, 'update'])->name('inventory.update');

    // Delete inventory
    Route::delete('/inventory/{inventory}', [InventoryController::class, 'destroy'])->name('inventory.destroy');

    
});

Route::middleware(['auth'])->group(function () {
    Route::resource('inventory', InventoryController::class)->except(['show']);

    Route::patch('/inventory/{inventory}/approve',
        [InventoryController::class, 'approve']
    )->name('inventory.approve');
    
    
});


// Admin-only routes
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/inventory/pending', [InventoryController::class, 'pending'])->name('inventory.pending');
    Route::post('/inventory/{inventory}/approve', [InventoryController::class, 'approve'])->name('inventory.approve');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    
});


Route::middleware(['auth'])->group(function () {
    Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
    Route::get('/stock/create', [StockController::class, 'create'])->name('stock.create');
    Route::post('/stock', [StockController::class, 'store'])->name('stock.store');
});


Route::get('/inventory/download-pdf',
    [InventoryController::class, 'downloadPdf']
)->name('inventory.download.pdf');



require __DIR__.'/auth.php';

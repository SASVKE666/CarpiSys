<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\WorkOrderController;


Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [DashboardController::class,  'index'])->name('dashboard');
    Route::resource('items', ItemController::class)->only(['index','store','update','destroy']);
    Route::get('/inventory',           [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventory/movement', [InventoryController::class, 'movement'])->name('inventory.movement');
    Route::resource('work-orders', WorkOrderController::class)->only(['index','store','update']);
    Route::post('/work-orders/{workOrder}/idle-time', [WorkOrderController::class, 'idleTime'])
         ->name('work-orders.idle-time');
    Route::post('/inventory/stock', [InventoryController::class, 'saveStock'])->name('inventory.stock');
});


require __DIR__.'/auth.php';

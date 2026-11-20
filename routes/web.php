<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Permisos por rol
|--------------------------------------------------------------------------
| operario : ve el sistema, registra movimientos de inventario, cambia el estado
|            de las órdenes y registra tiempos muertos.
| admin    : además crea/edita/desactiva artículos, crea y edita órdenes (precios y
|            costos), ajusta existencias directamente y administra usuarios.
|
| No hay registro público: las cuentas las crea el administrador (/users) o el
| comando "php artisan carpisys:crear-usuario".
*/

// Cada usuario edita su propio perfil. No se permite que se borre solo: dar de baja
// una cuenta lo hace el administrador desde /users, para no perder el historial.
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

// Cualquier usuario activo
Route::middleware(['auth', 'verified', 'active'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::post('/inventory/movement', [InventoryController::class, 'movement'])->name('inventory.movement');

    // El controlador limita a los operarios a cambiar solo el estado (ver WorkOrderController@update)
    Route::put('/work-orders/{workOrder}', [WorkOrderController::class, 'update'])->name('work-orders.update');
    Route::post('/work-orders/{workOrder}/idle-time', [WorkOrderController::class, 'idleTime'])
         ->name('work-orders.idle-time');
});

// Solo administrador
Route::middleware(['auth', 'verified', 'active', 'role:admin'])->group(function () {
    Route::resource('items', ItemController::class)->only(['store', 'update', 'destroy']);
    Route::post('/work-orders', [WorkOrderController::class, 'store'])->name('work-orders.store');
    Route::post('/inventory/stock', [InventoryController::class, 'saveStock'])->name('inventory.stock');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
});

require __DIR__.'/auth.php';

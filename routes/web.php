<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\OverheadRateController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\WorkOrderEstimateController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkOrderController;
use App\Http\Controllers\WorkOrderCostController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Permisos por rol
|--------------------------------------------------------------------------
| operario : ve el sistema, registra movimientos de inventario, cambia el estado
|            de las órdenes y registra tiempos muertos.
| admin    : además crea/edita/desactiva artículos, crea y edita órdenes (precios y
|            costos), ajusta existencias directamente, borra horas registradas y administra usuarios.
|
| Los operarios tampoco reciben datos financieros (costos, precios, márgenes, tarifas):
| el filtro está en DashboardController, no solo en la interfaz.
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
    // Recibir mercancía de una orden de compra (entradas de inventario). Los operarios no ven importes.
    Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])
         ->name('purchase-orders.receive');
    Route::post('/work-orders/{workOrder}/idle-time', [WorkOrderController::class, 'idleTime'])
         ->name('work-orders.idle-time');

    // Materiales (descuentan inventario) y horas de una orden; recalculan MD + MOD + CIF
    Route::post('/work-orders/{workOrder}/materials', [WorkOrderCostController::class, 'addMaterial'])
         ->name('work-orders.materials.store');
    Route::delete('/work-orders/{workOrder}/materials/{material}', [WorkOrderCostController::class, 'removeMaterial'])
         ->name('work-orders.materials.destroy');
    Route::post('/work-orders/{workOrder}/labor', [WorkOrderCostController::class, 'addLabor'])
         ->name('work-orders.labor.store');
});

// Solo administrador
Route::middleware(['auth', 'verified', 'active', 'role:admin'])->group(function () {
    Route::resource('items', ItemController::class)->only(['store', 'update', 'destroy']);
    Route::post('/work-orders', [WorkOrderController::class, 'store'])->name('work-orders.store');
    Route::post('/inventory/stock', [InventoryController::class, 'saveStock'])->name('inventory.stock');
    // Material estimado de la orden (el plan contra el que se compara lo registrado)
    Route::post('/work-orders/{workOrder}/estimates', [WorkOrderEstimateController::class, 'store'])
         ->name('work-orders.estimates.store');
    Route::delete('/work-orders/{workOrder}/estimates/{estimate}', [WorkOrderEstimateController::class, 'destroy'])
         ->name('work-orders.estimates.destroy');
    Route::delete('/work-orders/{workOrder}/labor/{labor}', [WorkOrderCostController::class, 'removeLabor'])
         ->name('work-orders.labor.destroy');

    // Compras: el administrador crea, edita, envía, cancela y borra; proveedores
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
    Route::put('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('purchase-orders.update');
    Route::delete('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->name('purchase-orders.destroy');
    Route::post('/purchase-orders/{purchaseOrder}/send', [PurchaseOrderController::class, 'send'])->name('purchase-orders.send');
    Route::post('/purchase-orders/{purchaseOrder}/close', [PurchaseOrderController::class, 'close'])->name('purchase-orders.close');
    Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
    Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');

    Route::get('/reports/movimientos-semanales.csv', [ReportController::class, 'weeklyCsv'])->name('reports.weekly-csv');
    Route::get('/reports/movimientos-diarios.csv', [ReportController::class, 'dailyCsv'])->name('reports.daily-csv');

    Route::get('/overhead-rates', [OverheadRateController::class, 'index'])->name('overhead-rates.index');
    Route::post('/overhead-rates', [OverheadRateController::class, 'store'])->name('overhead-rates.store');
    Route::put('/overhead-rates/{overheadRate}', [OverheadRateController::class, 'update'])->name('overhead-rates.update');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
});

require __DIR__.'/auth.php';

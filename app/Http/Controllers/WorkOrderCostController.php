<?php

namespace App\Http\Controllers;

use App\Models\LaborRecord;
use App\Models\WorkOrder;
use App\Models\WorkOrderMaterial;
use App\Services\CostingService;
use App\Services\InventoryService;
use Illuminate\Http\Request;

/**
 * Materiales y horas de una orden de trabajo. Cada alta o baja descuenta/devuelve inventario
 * (materiales) y recalcula MD + MOD + CIF de la orden.
 */
class WorkOrderCostController extends Controller
{
    public function addMaterial(Request $request, WorkOrder $workOrder, InventoryService $inventory)
    {
        $data = $request->validate([
            'item_id'     => 'required|exists:items,id',
            'location_id' => 'required|exists:locations,id',
            'quantity'    => 'required|numeric|min:0.001',
            'notes'       => 'nullable|string|max:500',
        ]);

        $inventory->consumeForOrder(
            $request->user(), $workOrder, (int) $data['item_id'], (int) $data['location_id'],
            (float) $data['quantity'], $data['notes'] ?? null,
        );

        return back()->with('success', 'Material registrado y descontado del inventario.');
    }

    /** Quita un material y lo devuelve al inventario. Lo puede hacer el administrador o quien lo registró. */
    public function removeMaterial(Request $request, WorkOrder $workOrder, WorkOrderMaterial $material, InventoryService $inventory)
    {
        abort_unless($material->work_order_id === $workOrder->id, 404);

        $registeredBy = $material->movement?->user_id;
        abort_unless(
            $request->user()->isAdmin() || ($registeredBy !== null && $registeredBy === $request->user()->id),
            403, 'Solo el administrador o quien registró el material puede quitarlo.'
        );

        $inventory->returnMaterial($request->user(), $material);

        return back()->with('success', 'Material devuelto al inventario.');
    }

    public function addLabor(Request $request, WorkOrder $workOrder, CostingService $costing)
    {
        $data = $request->validate([
            'hours'            => 'required|numeric|min:0.25|max:24',
            'work_date'        => 'nullable|date|before_or_equal:today',
            'task_description' => 'nullable|string|max:255',
            // Solo los usa el administrador (personal sin cuenta); se ignoran para operarios.
            'worker_name'      => 'nullable|string|max:120',
            'hourly_rate'      => 'nullable|numeric|min:0',
        ]);

        $isAdmin = $request->user()->isAdmin();

        $costing->logLabor(
            $request->user(), $workOrder, (float) $data['hours'], $data['work_date'] ?? null,
            $data['task_description'] ?? null,
            $isAdmin ? ($data['worker_name'] ?? null) : null,
            $isAdmin && isset($data['hourly_rate']) ? (float) $data['hourly_rate'] : null,
        );

        return back()->with('success', 'Horas registradas.');
    }

    /** Solo administrador (ver routes/web.php): borrar horas cambia el costo de mano de obra. */
    public function removeLabor(WorkOrder $workOrder, LaborRecord $labor, CostingService $costing)
    {
        abort_unless($labor->work_order_id === $workOrder->id, 404);

        $costing->removeLabor($labor);

        return back()->with('success', 'Registro de horas eliminado.');
    }
}

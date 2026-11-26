<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\WorkOrder;
use App\Models\WorkOrderEstimate;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Material estimado de una orden (solo administrador, ver routes/web.php). No mueve inventario
 * ni cambia costos: es el plan contra el que se compara lo que realmente se registra.
 */
class WorkOrderEstimateController extends Controller
{
    /** Agrega el artículo al estimado o, si ya estaba, cambia su cantidad. */
    public function store(Request $request, WorkOrder $workOrder)
    {
        $this->ensureOpen($workOrder);

        $data = $request->validate([
            'item_id'  => 'required|exists:items,id',
            'quantity' => 'required|numeric|min:0.001|max:99999999',
        ]);

        $item = Item::findOrFail($data['item_id']);
        if ($item->item_type === 'herramienta') {
            throw ValidationException::withMessages(['item_id' => 'Las herramientas no se consumen; no van en el estimado.']);
        }

        WorkOrderEstimate::updateOrCreate(
            ['work_order_id' => $workOrder->id, 'item_id' => $item->id],
            ['quantity' => $data['quantity']],
        );

        return back()->with('success', 'Material estimado guardado.');
    }

    public function destroy(WorkOrder $workOrder, WorkOrderEstimate $estimate)
    {
        abort_unless($estimate->work_order_id === $workOrder->id, 404);
        $this->ensureOpen($workOrder);

        $estimate->delete();

        return back()->with('success', 'Material quitado del estimado.');
    }

    private function ensureOpen(WorkOrder $workOrder): void
    {
        if (! $workOrder->isOpen()) {
            throw ValidationException::withMessages([
                'quantity' => 'La orden está ' . $workOrder->status . ': ya no admite cambios al material estimado.',
            ]);
        }
    }
}

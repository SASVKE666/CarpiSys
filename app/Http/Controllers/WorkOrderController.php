<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkOrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = \App\Models\WorkOrder::with('creator')
            ->when($request->status, fn($q, $s) => $q->byStatus($s))
            ->orderByDesc('created_at')
            ->paginate(15);

        // Agregar atributos calculados
        $orders->getCollection()->transform(fn($o) => $o->append([
            'total_cost','profit','margin_pct'
        ]));

        return Inertia::render('WorkOrders/Index', [
            'orders'  => $orders,
            'summary' => [
                'pendiente'  => \App\Models\WorkOrder::byStatus('pendiente')->count(),
                'en_proceso' => \App\Models\WorkOrder::byStatus('en_proceso')->count(),
                'completada' => \App\Models\WorkOrder::byStatus('completada')->count(),
            ],
        ]);
    }

    // Estados permitidos (coinciden con el enum de la migración work_orders)
    private const STATUSES = 'pendiente,en_proceso,pausada,completada,cancelada';

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_name'    => 'required|string|max:150',
            'description'    => 'required|string',
            'status'         => 'nullable|in:' . self::STATUSES,
            'start_date'     => 'nullable|date',
            'materials_cost' => 'nullable|numeric|min:0',
            'labor_cost'     => 'nullable|numeric|min:0',
            'overhead_cost'  => 'nullable|numeric|min:0',
            'quoted_price'   => 'nullable|numeric|min:0',
            'machine_hours'  => 'nullable|numeric|min:0',
            'notes'          => 'nullable|string',
        ]);

        $data['machine_hours']  = $data['machine_hours']  ?? 0;
        $data['status']         = $data['status']         ?? 'pendiente';
        $data['materials_cost'] = $data['materials_cost'] ?? 0;
        $data['labor_cost']     = $data['labor_cost']     ?? 0;
        $data['overhead_cost']  = $data['overhead_cost']  ?? 0;
        $data['quoted_price']   = $data['quoted_price']   ?? 0;
        $data['created_by']     = auth()->id();

        \App\Models\WorkOrder::create($data);

        return redirect()->route('dashboard');
    }

    // Actualiza una orden existente. Acepta cambios parciales (por ejemplo,
    // solo { status }) gracias a "sometimes": solo se valida lo que se envía.
    public function update(Request $request, \App\Models\WorkOrder $workOrder)
    {
        $data = $request->validate([
            'client_name'    => 'sometimes|required|string|max:150',
            'description'    => 'sometimes|required|string',
            'status'         => 'sometimes|required|in:' . self::STATUSES,
            'start_date'     => 'nullable|date',
            'materials_cost' => 'sometimes|required|numeric|min:0',
            'labor_cost'     => 'sometimes|required|numeric|min:0',
            'overhead_cost'  => 'sometimes|required|numeric|min:0',
            'quoted_price'   => 'sometimes|required|numeric|min:0',
            'machine_hours'  => 'sometimes|required|numeric|min:0',
            'notes'          => 'nullable|string',
        ]);

        // Los operarios solo pueden cambiar el estado de la orden; precios, costos y
        // datos del cliente los gestiona el administrador.
        if (! $request->user()->isAdmin()) {
            abort_unless(array_keys($data) === ['status'], 403, 'Solo el administrador puede editar los datos de la orden.');
        }

        // Con materiales u horas registrados, los costos los calcula el sistema: se ignoran los
        // valores escritos a mano para que no pisen el cálculo.
        $auto = $workOrder->usesAutoCosting();
        if ($auto) {
            unset($data['materials_cost'], $data['labor_cost'], $data['overhead_cost']);
        }

        // Antes se llamaba a create(), lo que duplicaba la orden en cada edición.
        $workOrder->update($data);

        // Cambiar las horas de máquina modifica el CIF de la orden.
        if ($auto && array_key_exists('machine_hours', $data)) {
            $workOrder->recalculateCosts();
        }

        return redirect()->route('dashboard');
    }

    // Registrar tiempo muerto (instrumento de medición)
    public function idleTime(Request $request, \App\Models\WorkOrder $workOrder)
    {
        $data = $request->validate([
            'cause'        => 'required|in:busqueda_material,calculo_manual,retraso_compra,falta_herramienta,retrabajo,otro',
            'duration_min' => 'required|numeric|min:1',
            'worker_name'  => 'nullable|string|max:120',
            'notes'        => 'nullable|string',
        ]);
        $data['work_order_id'] = $workOrder->id;
        $data['recorded_by']   = auth()->id();
        \App\Models\IdleTimeRecord::create($data);
        return redirect()->route('dashboard');
    }
}
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

    public function store(Request $request)
{
    $data = $request->validate([
        'client_name'    => 'required|string|max:150',
        'description'    => 'required|string',
        'status'         => 'nullable|string',
        'start_date'     => 'nullable|date',
        'materials_cost' => 'nullable|numeric|min:0',
        'labor_cost'     => 'nullable|numeric|min:0',
        'overhead_cost'  => 'nullable|numeric|min:0',
        'quoted_price'   => 'nullable|numeric|min:0',
        'notes'          => 'nullable|string',
    ]);

    $data['status']         = $data['status']         ?? 'pendiente';
    $data['materials_cost'] = $data['materials_cost'] ?? 0;
    $data['labor_cost']     = $data['labor_cost']     ?? 0;
    $data['overhead_cost']  = $data['overhead_cost']  ?? 0;
    $data['quoted_price']   = $data['quoted_price']   ?? 0;
    $data['created_by']     = auth()->id() ?? null;

    \App\Models\WorkOrder::create($data);

    return redirect()->route('dashboard');
}

    public function update(Request $request, \App\Models\WorkOrder $workOrder)
    {
        $data = $request->validate([
    'client_name'    => 'required|string|max:150',
    'description'    => 'required|string',
    'status'         => 'nullable|string',
    'start_date'     => 'nullable|date',
    'materials_cost' => 'required|numeric|min:0',
    'labor_cost'     => 'required|numeric|min:0',
    'overhead_cost'  => 'required|numeric|min:0',
    'quoted_price'   => 'required|numeric|min:0',
    'notes'          => 'nullable|string',
]);
$data['created_by'] = auth()->id() ?? null;
if(empty($data['status'])) $data['status'] = 'pendiente';

\App\Models\WorkOrder::create($data);
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
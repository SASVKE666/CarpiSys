<?php
namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Item;
use App\Models\LaborRecord;
use App\Models\Location;
use App\Models\OverheadRate;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\WorkOrder;
use App\Services\MaterialVarianceService;
use App\Services\MovementReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user    = $request->user();
        $isAdmin = $user->isAdmin();

        // DATOS FINANCIEROS: costos, precios, márgenes y tarifas solo se envían al administrador.
        // Para los operarios llegan como null; ocultarlos solo en la interfaz no bastaría,
        // porque cualquiera puede ver las props de la página en el navegador.
        $money = fn ($value) => $isAdmin ? (float) $value : null;

        $items = Item::with('category')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($i) => [
                'id'          => $i->id,
                'name'        => $i->name,
                'sku'         => $i->sku,
                'type'        => $i->item_type,
                'category_id' => $i->category_id,
                'category'    => $i->category?->name ?? 'Sin categoría',
                'unit'        => $i->unit,
                'cost'        => $money($i->unit_cost),
                'notes'       => $i->notes ?? '',
            ]);

        $inv = InventoryStock::with('location')
            ->get()
            ->map(fn ($s) => [
                'id'          => $s->id,
                'item_id'     => $s->item_id,
                'location_id' => $s->location_id,
                'location'    => $s->location?->name ?? '',
                'qty'         => (float) $s->quantity,
                'min_qty'     => (float) $s->min_quantity,
                'reserved'    => (float) $s->reserved_qty,
            ]);

        $variance = app(MaterialVarianceService::class);

        $orderModels = WorkOrder::with(['materials.item', 'materials.location', 'materials.movement.user', 'laborRecords', 'estimates.item'])
            ->orderByDesc('created_at')
            ->get();

        $orders = $orderModels
            ->map(fn ($o) => [
                'id'            => $o->id,
                'code'          => $o->code,
                'client'        => $o->client_name,
                'desc'          => $o->description,
                'status'        => $o->status,
                'is_open'       => $o->isOpen(),
                'auto'          => $o->materials->isNotEmpty() || $o->laborRecords->isNotEmpty(),
                'date'          => $o->start_date?->format('Y-m-d') ?? $o->created_at->format('Y-m-d'),
                'mat'           => $money($o->materials_cost),
                'labor'         => $money($o->labor_cost),
                'overhead'      => $money($o->overhead_cost),
                'quoted'        => $money($o->quoted_price),
                'machine_hours' => $isAdmin ? (float) $o->machine_hours : null,
                // Material estimado frente a lo registrado (solo cantidades, no importes)
                'estimates'     => $variance->lines($o),
                'materials'     => $o->materials->map(fn ($m) => [
                    'id'        => $m->id,
                    'item_id'   => $m->item_id,
                    'name'      => $m->item?->name ?? '—',
                    'unit'      => $m->item?->unit ?? '',
                    'qty'       => (float) $m->qty_actual,
                    'location'  => $m->location?->name ?? '',
                    'date'      => $m->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                    'by'        => $m->movement?->user?->name ?? '',
                    'cost'      => $money($m->unit_cost),
                    'total'     => $money($m->qty_actual * $m->unit_cost),
                    'can_remove'=> $isAdmin || ($m->movement?->user_id !== null && $m->movement->user_id === $user->id),
                ])->values(),
                'labor_records' => $o->laborRecords->map(fn ($l) => [
                    'id'     => $l->id,
                    'worker' => $l->worker_name,
                    'date'   => $l->work_date?->format('Y-m-d'),
                    'hours'  => (float) $l->hours,
                    'task'   => $l->task_description ?? '',
                    'rate'   => $money($l->hourly_rate),
                    'total'  => $money($l->hours * $l->hourly_rate),
                ])->values(),
            ]);

        // Últimos movimientos de inventario (con el código de la orden cuando vienen de una)
        $movs = InventoryMovement::with(['item', 'location', 'user'])
            ->orderByDesc('id')->limit(40)->get();
        $orderCodes = WorkOrder::whereIn('id', $movs->where('reference_type', 'work_order')->pluck('reference_id')->filter())
            ->pluck('code', 'id');
        $purchaseCodes = PurchaseOrder::whereIn('id', $movs->where('reference_type', 'purchase_order')->pluck('reference_id')->filter())
            ->pluck('code', 'id');
        $movements = $movs->map(fn ($m) => [
            'id'       => $m->id,
            'date'     => $m->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            'type'     => $m->movement_type,
            'item'     => $m->item?->name ?? '—',
            'unit'     => $m->item?->unit ?? '',
            'qty'      => (float) $m->quantity,
            'location' => $m->location?->name ?? '',
            'by'       => $m->user?->name ?? '',
            'order'    => match ($m->reference_type) {
                'work_order'     => $orderCodes[$m->reference_id] ?? '',
                'purchase_order' => $purchaseCodes[$m->reference_id] ?? '',
                default          => '',
            },
            'cost'     => $money($m->unit_cost),
        ]);

        // Órdenes de compra. Los operarios ven qué se pidió y qué falta por llegar, pero no los importes.
        $purchases = PurchaseOrder::with(['supplier', 'items.item'])
            ->orderByDesc('id')->limit(100)->get()
            ->map(fn ($p) => [
                'id'          => $p->id,
                'code'        => $p->code,
                'supplier_id' => $p->supplier_id,
                'supplier'    => $p->supplier?->name ?? '—',
                'status'      => $p->status,
                'ordered'     => $p->ordered_date?->format('Y-m-d'),
                'expected'    => $p->expected_date?->format('Y-m-d'),
                'received'    => $p->received_date?->format('Y-m-d'),
                'notes'       => $p->notes ?? '',
                'tax_rate'    => $isAdmin ? (float) $p->tax_rate : null,
                'subtotal'    => $money($p->subtotal),
                'tax'         => $money($p->tax),
                'total'       => $money($p->total),
                'lines'       => $p->items->map(fn ($l) => [
                    'id'       => $l->id,
                    'item_id'  => $l->item_id,
                    'name'     => $l->item?->name ?? '—',
                    'sku'      => $l->item?->sku ?? '',
                    'unit'     => $l->item?->unit ?? '',
                    'ordered'  => (float) $l->quantity_ordered,
                    'received' => (float) $l->quantity_received,
                    'cost'     => $money($l->unit_cost),
                    'subtotal' => $money($l->quantity_ordered * $l->unit_cost),
                ])->values(),
            ]);

        return Inertia::render('Dashboard', [
            'purchasesData'  => $purchases,
            'suppliersData'  => Supplier::orderBy('name')->get()->map(fn ($s) => [
                'id' => $s->id, 'name' => $s->name, 'contact' => $s->contact ?? '', 'phone' => $s->phone ?? '',
                'email' => $s->email ?? '', 'address' => $s->address ?? '', 'notes' => $s->notes ?? '',
                'is_active' => (bool) $s->is_active,
            ]),
            'itemsData'      => $items,
            'invData'        => $inv,
            'ordersData'     => $orders,
            'movementsData'  => $movements,
            'categoriesData' => Category::orderBy('name')->get(['id', 'name']),
            'locationsData'  => Location::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            // Horas que esta persona registró hoy (no es un dato financiero)
            'myHoursToday'   => (float) LaborRecord::where('user_id', $user->id)
                                    ->whereDate('work_date', now()->toDateString())->sum('hours'),
            // Aviso para el administrador: sin tasas activas, el CIF calculado sería $0
            'hasOverheadRates' => $isAdmin ? OverheadRate::active()->exists() : null,
            // Conteo semanal de movimientos (sin importes): solo se envía al administrador, que ve Reportes
            'weeklyMovements'  => $isAdmin ? app(MovementReportService::class)->weekly(12) : null,
            'dailyMovements'   => $isAdmin ? app(MovementReportService::class)->daily(14) : null,
            // Material estimado vs. registrado de las órdenes completadas (para el estudio)
            'varianceSummary'  => $isAdmin ? $variance->summary($orderModels) : null,
        ]);
    }
}

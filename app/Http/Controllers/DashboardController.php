<?php
namespace App\Http\Controllers;
 
use App\Models\Item;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
class DashboardController extends Controller
{
    public function index()
    {
        $items = \App\Models\Item::with('category')
        ->where('is_active', true)
        ->orderBy('name')
        ->get()
        ->map(fn($i) => [
            'id'          => $i->id,
            'name'        => $i->name,
            'sku'         => $i->sku,
            'type'        => $i->item_type,
            'category_id' => $i->category_id,
            'category'    => $i->category?->name ?? 'Sin categoría',
            'unit'     => $i->unit,
            'cost'     => (float) $i->unit_cost,
            'notes'    => $i->notes ?? '',
        ]);

    $inv = \App\Models\InventoryStock::with('location')
        ->get()
        ->map(fn($s) => [
            'id'          => $s->id,
            'item_id'     => $s->item_id,
            'location_id' => $s->location_id,
            'location'    => $s->location?->name ?? '',
            'qty'      => (float) $s->quantity,
            'min_qty'  => (float) $s->min_quantity,
            'reserved' => (float) $s->reserved_qty,
        ]);

    $orders = \App\Models\WorkOrder::orderByDesc('created_at')
        ->get()
        ->map(fn($o) => [
            'id'       => $o->id,
            'code'     => $o->code,
            'client'   => $o->client_name,
            'desc'     => $o->description,
            'status'   => $o->status,
            'date'     => $o->start_date?->format('Y-m-d') ?? $o->created_at->format('Y-m-d'),
            'mat'      => (float) $o->materials_cost,
            'labor'    => (float) $o->labor_cost,
            'overhead' => (float) $o->overhead_cost,
            'quoted'   => (float) $o->quoted_price,
        ]);

    return Inertia::render('Dashboard', [
        'itemsData'      => $items,
        'invData'        => $inv,
        'ordersData'     => $orders,
        // Catálogos reales para los formularios (antes estaban fijos en el JSX)
        'categoriesData' => \App\Models\Category::orderBy('name')->get(['id', 'name']),
        'locationsData'  => \App\Models\Location::where('is_active', true)->orderBy('name')->get(['id', 'name']),
    ]);
       /*  $lowStockItems = \App\Models\InventoryStock::with('item','location')
            ->whereColumn('quantity', '<=', 'min_quantity')
            ->get();
 
        $totalInventoryValue = \App\Models\InventoryStock::join('items','items.id','=','inventory_stocks.item_id')
            ->selectRaw('SUM(inventory_stocks.quantity * items.unit_cost) as total')
            ->value('total') ?? 0;
 
        $completedOrders = \App\Models\WorkOrder::byStatus('completada')->get();
        $totalRevenue  = $completedOrders->sum('quoted_price');
        $totalRealCost = $completedOrders->sum(fn($o) => $o->total_cost);
        $avgMargin     = $totalRevenue > 0
            ? round(($totalRevenue - $totalRealCost) / $totalRevenue * 100, 1)
            : 0;

        return Inertia::render('Dashboard', [
            'kpis' => [
                'inventory_value'  => $totalInventoryValue,
                'active_orders'    => \App\Models\WorkOrder::byStatus('en_proceso')->count(),
                'pending_orders'   => \App\Models\WorkOrder::byStatus('pendiente')->count(),
                'low_stock_count'  => $lowStockItems->count(),
                'avg_margin'       => $avgMargin,
            ],
            'recentOrders'  => \App\Models\WorkOrder::orderByDesc('created_at')->take(5)->get()->append(['total_cost','profit','margin_pct']),
            'lowStockItems' => $lowStockItems,
        ]); */
    }
}
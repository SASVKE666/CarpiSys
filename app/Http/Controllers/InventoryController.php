<?php
namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InventoryController extends Controller
{
    public function index()
    {
        $stocks = \App\Models\InventoryStock::with(['item.category','location'])
            ->get()
            ->map(fn($s) => [
                ...$s->toArray(),
                'available'  => $s->available,
                'is_low'     => $s->is_low,
                'item_name'  => $s->item->name,
                'item_sku'   => $s->item->sku,
                'item_type'  => $s->item->item_type,
                'unit'       => $s->item->unit,
            ]);

        return Inertia::render('Inventory/Index', [
            'stocks'    => $stocks,
            'locations' => \App\Models\Location::where('is_active',true)->get(),
            'items'     => Item::active()->orderBy('name')->get(['id','name','sku','unit']),
        ]);
    }

    public function movement(Request $request)
    {
        $data = $request->validate([
            'item_id'       => 'required|exists:items,id',
            'location_id'   => 'required|exists:locations,id',
            'movement_type' => 'required|in:entrada,salida,ajuste,merma,traspaso,devolucion',
            'quantity'      => 'required|numeric|min:0.001',
            'unit_cost'     => 'nullable|numeric|min:0',
            'notes'         => 'nullable|string',
        ]);
        $data['user_id'] = $request->auth()->id();

        \App\Models\InventoryMovement::create($data); // El observer actualiza stock
        return back()->with('success', 'Movimiento registrado.');
    }

    public function saveStock(Request $request)
    {
        $data = $request->validate([
            'item_id'   => 'required|exists:items,id',
            'location'  => 'required|string',
            'qty'       => 'required|numeric|min:0',
            'min_qty'   => 'required|numeric|min:0',
            'reserved'  => 'required|numeric|min:0',
        ]);

        $location = \App\Models\Location::firstOrCreate(['name' => $data['location']]);

        \App\Models\InventoryStock::updateOrCreate(
            ['item_id' => $data['item_id'], 'location_id' => $location->id],
            [
                'quantity'     => $data['qty'],
                'min_quantity' => $data['min_qty'],
                'reserved_qty' => $data['reserved'],
            ]
        );

        return redirect()->route('dashboard');
    }
}

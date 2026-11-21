<?php
namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Category;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    /**
     * Entradas, salidas, mermas y devoluciones. El stock lo actualiza el observer de InventoryMovement.
     * Solo el administrador puede fijar el costo de una entrada (eso actualiza el costo promedio).
     */
    public function movement(Request $request, InventoryService $inventory)
    {
        $data = $request->validate([
            'item_id'       => 'required|exists:items,id',
            'location_id'   => 'required|exists:locations,id',
            'movement_type' => ['required', Rule::in(InventoryService::TYPES)],
            'quantity'      => 'required|numeric|min:0.001',
            'unit_cost'     => 'nullable|numeric|min:0',
            'notes'         => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $unitCost = ($user->isAdmin() && $data['movement_type'] === 'entrada' && isset($data['unit_cost']))
            ? (float) $data['unit_cost']
            : null;

        $inventory->move(
            $user, (int) $data['item_id'], (int) $data['location_id'], $data['movement_type'],
            (float) $data['quantity'], $unitCost, $data['notes'] ?? null,
        );

        return back()->with('success', 'Movimiento registrado.');
    }

    public function saveStock(Request $request)
    {
        $data = $request->validate([
            'item_id'     => 'required|exists:items,id',
            // Se elige una ubicación existente (antes era texto libre y creaba duplicados)
            'location_id' => 'required|exists:locations,id',
            'qty'         => 'required|numeric|min:0',
            'min_qty'     => 'required|numeric|min:0',
            'reserved'    => 'required|numeric|min:0',
        ]);

        \App\Models\InventoryStock::updateOrCreate(
            ['item_id' => $data['item_id'], 'location_id' => $data['location_id']],
            [
                'quantity'     => $data['qty'],
                'min_quantity' => $data['min_qty'],
                'reserved_qty' => $data['reserved'],
            ]
        );

        return redirect()->route('dashboard');
    }
}

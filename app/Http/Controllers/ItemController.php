<?php 
namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
class ItemController extends Controller
{
    public function index(Request $request)
    {
        $items = Item::with(['category','stocks.location'])
            ->active()
            ->when($request->search, fn($q, $s) =>
                $q->where('name','like',"%$s%")->orWhere('sku','like',"%$s%")
            )
            ->when($request->type, fn($q, $t) => $q->byType($t))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Items/Index', [
            'items'      => $items,
            'categories' => Category::orderBy('name')->get(),
            'filters'    => $request->only(['search','type']),
            'lowStock'   => Item::lowStock()->count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:200',
            'sku'         => 'required|string|max:60|unique:items',
            'item_type'   => 'required|in:materia_prima,herramienta,producto,consumible,otro',
            'category_id' => 'nullable|exists:categories,id',
            'unit'        => 'required|string|max:40',
            'unit_cost'   => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'notes'       => 'nullable|string',
        ]);
        $data['created_by'] = auth()->id();
        Item::create($data);
        return redirect()->route('dashboard');
    }

    public function update(Request $request, Item $item)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:200',
            'sku'         => "required|string|max:60|unique:items,sku,{$item->id}",
            'item_type'   => 'required|in:materia_prima,herramienta,producto,consumible,otro',
            'category_id' => 'nullable|exists:categories,id',
            'unit'        => 'required|string|max:40',
            'unit_cost'   => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'notes'       => 'nullable|string',
            'is_active'   => 'boolean',
        ]);
        $item->update($data);
        return redirect()->route('dashboard');
    }

    public function destroy(Item $item)
    {
        $item->update(['is_active' => false]); // Soft delete lógico
        return redirect()->route('dashboard');
    }
}

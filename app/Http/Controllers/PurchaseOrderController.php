<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Services\PurchasingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Órdenes de compra. Crear, editar, enviar, cancelar y borrar es del administrador (ver routes/web.php);
 * recibir la mercancía lo puede hacer también un operario (quien está en el taller cuando llega el pedido).
 */
class PurchaseOrderController extends Controller
{
    private function rules(): array
    {
        return [
            'supplier_id'       => ['required', Rule::exists('suppliers', 'id')->where('is_active', true)],
            'expected_date'     => 'nullable|date',
            'tax_rate'          => 'nullable|numeric|min:0|max:100',
            'notes'             => 'nullable|string|max:1000',
            'items'             => 'required|array|min:1|max:100',
            'items.*.item_id'   => ['required', 'distinct', Rule::exists('items', 'id')->where('is_active', true)],
            'items.*.quantity'  => 'required|numeric|min:0.001|max:99999999',
            'items.*.unit_cost' => 'required|numeric|min:0|max:99999999',
        ];
    }

    private const MESSAGES = [
        'supplier_id.required'    => 'Elige un proveedor.',
        'supplier_id.exists'      => 'El proveedor no existe o está desactivado.',
        'items.required'          => 'Agrega al menos un artículo.',
        'items.min'               => 'Agrega al menos un artículo.',
        'items.*.item_id.distinct'=> 'Un artículo aparece repetido; junta las cantidades en un solo renglón.',
        'items.*.quantity.required' => 'Falta la cantidad en un renglón.',
        'items.*.quantity.min'    => 'La cantidad de cada renglón debe ser mayor que cero.',
        'items.*.unit_cost.required' => 'Falta el costo unitario en un renglón.',
    ];

    public function store(Request $request, PurchasingService $purchasing)
    {
        $data = $request->validate($this->rules(), self::MESSAGES);
        $po = $purchasing->create($request->user(), $data);

        return back()->with('success', "Orden {$po->code} creada en borrador.");
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder, PurchasingService $purchasing)
    {
        $data = $request->validate($this->rules(), self::MESSAGES);
        $purchasing->update($purchaseOrder, $data);

        return back()->with('success', "Orden {$purchaseOrder->code} actualizada.");
    }

    public function send(PurchaseOrder $purchaseOrder, PurchasingService $purchasing)
    {
        $purchasing->send($purchaseOrder);
        return back()->with('success', "Orden {$purchaseOrder->code} marcada como enviada al proveedor.");
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder, PurchasingService $purchasing)
    {
        $data = $request->validate([
            'lines'               => 'required|array|min:1',
            'lines.*.line_id'     => 'required|integer',
            'lines.*.quantity'    => 'nullable|numeric|min:0|max:99999999',
            'lines.*.location_id' => 'nullable|exists:locations,id',
            'lines.*.unit_cost'   => 'nullable|numeric|min:0|max:99999999',
        ]);

        $po = $purchasing->receive($request->user(), $purchaseOrder, $data['lines']);

        return back()->with('success', $po->status === 'recibida'
            ? "Orden {$po->code} recibida completa: el material ya está en inventario."
            : "Recepción parcial de {$po->code} registrada: el material ya está en inventario.");
    }

    public function close(PurchaseOrder $purchaseOrder, PurchasingService $purchasing)
    {
        $purchasing->closeShort($purchaseOrder);
        return back()->with('success', "Orden {$purchaseOrder->code} dada por recibida con lo que llegó.");
    }

    public function cancel(PurchaseOrder $purchaseOrder, PurchasingService $purchasing)
    {
        $purchasing->cancel($purchaseOrder);
        return back()->with('success', "Orden {$purchaseOrder->code} cancelada.");
    }

    public function destroy(PurchaseOrder $purchaseOrder, PurchasingService $purchasing)
    {
        $code = $purchaseOrder->code;
        $purchasing->delete($purchaseOrder);
        return back()->with('success', "Borrador {$code} eliminado.");
    }
}

<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Item;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderMaterial;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reglas del inventario perpetuo. Todo se hace dentro de una transacción para que el
 * stock, el movimiento y el costo de la orden nunca queden a medias.
 *
 * Método de costo: promedio ponderado por artículo (items.unit_cost). Una entrada con costo
 * unitario actualiza ese promedio; las salidas y los consumos usan el promedio vigente.
 */
class InventoryService
{
    /** Tipos que el sistema sabe aplicar al stock. ('ajuste' y 'traspaso' no se usan: ver saveStock). */
    public const TYPES = ['entrada', 'salida', 'merma', 'devolucion'];

    private const OUTGOING = ['salida', 'merma'];

    /**
     * Registra un movimiento y actualiza el stock (el observer de InventoryMovement suma o resta).
     * Las salidas y mermas no pueden dejar el stock en negativo.
     *
     * $unitCost solo se usa en 'entrada' (actualiza el costo promedio) y 'devolucion' (costo con
     * el que salió el material). En las demás se usa el costo promedio vigente.
     */
    public function move(
        User $user, int $itemId, int $locationId, string $type, float $quantity,
        ?float $unitCost = null, ?string $notes = null,
        ?string $referenceType = null, ?int $referenceId = null,
    ): InventoryMovement {
        if (! in_array($type, self::TYPES, true)) {
            throw ValidationException::withMessages(['movement_type' => 'Tipo de movimiento no válido.']);
        }
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'La cantidad debe ser mayor que cero.']);
        }

        return DB::transaction(function () use ($user, $itemId, $locationId, $type, $quantity, $unitCost, $notes, $referenceType, $referenceId) {
            $item  = Item::lockForUpdate()->findOrFail($itemId);
            $stock = InventoryStock::where('item_id', $itemId)->where('location_id', $locationId)->lockForUpdate()->first();

            if (in_array($type, self::OUTGOING, true)) {
                $available = $stock ? (float) $stock->quantity : 0.0;
                if ($quantity > $available + 0.00001) {
                    throw ValidationException::withMessages([
                        'quantity' => sprintf('Solo hay %s %s de "%s" en esa ubicación.', $this->fmt($available), $item->unit, $item->name),
                    ]);
                }
            }

            $cost = (float) $item->unit_cost;

            if ($type === 'entrada' && $unitCost !== null) {
                $onHand = max(0.0, (float) InventoryStock::where('item_id', $itemId)->sum('quantity'));
                $avg = ($onHand + $quantity) > 0
                    ? (($onHand * $cost) + ($quantity * $unitCost)) / ($onHand + $quantity)
                    : $unitCost;
                $item->unit_cost = round($avg, 2);
                $item->save();
                $cost = $unitCost;               // el movimiento registra el precio de esta compra
            } elseif ($type === 'devolucion' && $unitCost !== null) {
                $cost = $unitCost;               // vuelve al costo con el que salió
            }

            return InventoryMovement::create([
                'item_id'        => $itemId,
                'location_id'    => $locationId,
                'movement_type'  => $type,
                'quantity'       => $quantity,
                'unit_cost'      => $cost,
                'reference_type' => $referenceType ?? 'manual',
                'reference_id'   => $referenceId,
                'notes'          => $notes,
                'user_id'        => $user->id,
            ]);
        });
    }

    /**
     * Usa material en una orden: descuenta el inventario, deja el movimiento de salida
     * enlazado a la orden y recalcula los costos (MD, y con ello el CIF por porcentaje).
     */
    public function consumeForOrder(
        User $user, WorkOrder $order, int $itemId, int $locationId, float $quantity, ?string $notes = null,
    ): WorkOrderMaterial {
        return DB::transaction(function () use ($user, $order, $itemId, $locationId, $quantity, $notes) {
            $order = $this->lockOpenOrder($order);

            $movement = $this->move($user, $itemId, $locationId, 'salida', $quantity, null, $notes, 'work_order', $order->id);

            $material = WorkOrderMaterial::create([
                'work_order_id' => $order->id,
                'item_id'       => $itemId,
                'location_id'   => $locationId,
                'movement_id'   => $movement->id,
                'qty_estimated' => 0,
                'qty_actual'    => $quantity,
                'unit_cost'     => $movement->unit_cost,   // costo congelado al momento de usarlo
                'notes'         => $notes,
            ]);

            $order->recalculateCosts();

            return $material;
        });
    }

    /**
     * Quita un material de la orden y lo regresa al inventario (movimiento de devolución).
     */
    public function returnMaterial(User $user, WorkOrderMaterial $material): void
    {
        DB::transaction(function () use ($user, $material) {
            $order = $this->lockOpenOrder($material->workOrder);

            $this->move(
                $user, $material->item_id, $material->location_id, 'devolucion',
                (float) $material->qty_actual, (float) $material->unit_cost,
                'Material devuelto desde ' . $order->code, 'work_order', $order->id,
            );

            $material->delete();
            $order->recalculateCosts();
        });
    }

    private function lockOpenOrder(WorkOrder $order): WorkOrder
    {
        $order = WorkOrder::lockForUpdate()->findOrFail($order->id);
        if (! $order->isOpen()) {
            throw ValidationException::withMessages([
                'order' => "La orden {$order->code} está {$order->status}: ya no admite cambios de materiales ni horas. Cámbiala a \"En proceso\" si necesitas corregirla.",
            ]);
        }
        return $order;
    }

    private function fmt(float $n): string
    {
        return rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.') ?: '0';
    }
}

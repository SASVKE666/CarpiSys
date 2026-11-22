<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Órdenes de compra: borrador → enviada → parcial → recibida (o cancelada).
 *
 * Recibir mercancía genera una ENTRADA de inventario por cada renglón recibido (con el costo de la
 * compra, que actualiza el costo promedio del artículo). Todo ocurre en una transacción: o se
 * registra todo lo recibido o nada.
 */
class PurchasingService
{
    private const EPS = 0.00001;

    public function __construct(private InventoryService $inventory) {}

    /** @param array{supplier_id:int,expected_date?:?string,tax_rate?:?float,notes?:?string,items:array} $data */
    public function create(User $user, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($user, $data) {
            $po = PurchaseOrder::create([
                'supplier_id'   => $data['supplier_id'],
                'status'        => 'borrador',
                'expected_date' => $data['expected_date'] ?? null,
                'tax_rate'      => $data['tax_rate'] ?? 16,
                'notes'         => $data['notes'] ?? null,
                'created_by'    => $user->id,
            ]);
            $this->replaceLines($po, $data['items']);
            $po->recalculateTotals();
            return $po;
        });
    }

    /** Solo se edita mientras es borrador. */
    public function update(PurchaseOrder $po, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $data) {
            $po = $this->lock($po);
            $this->requireStatus($po, ['borrador'], 'Solo se puede editar una orden en borrador.');

            $po->fill([
                'supplier_id'   => $data['supplier_id'],
                'expected_date' => $data['expected_date'] ?? null,
                'tax_rate'      => $data['tax_rate'] ?? $po->tax_rate,
                'notes'         => $data['notes'] ?? null,
            ])->save();
            $this->replaceLines($po, $data['items']);
            $po->recalculateTotals();
            return $po;
        });
    }

    public function send(PurchaseOrder $po): PurchaseOrder
    {
        return DB::transaction(function () use ($po) {
            $po = $this->lock($po);
            $this->requireStatus($po, ['borrador'], 'Solo se puede enviar una orden en borrador.');
            if (! $po->items()->exists()) {
                throw ValidationException::withMessages(['order' => 'La orden no tiene artículos.']);
            }
            $po->status       = 'enviada';
            $po->ordered_date = now()->toDateString();
            $po->save();
            return $po;
        });
    }

    /**
     * Registra lo que llegó. Cada elemento de $lines: ['line_id', 'quantity', 'location_id', 'unit_cost'?].
     * Las cantidades en 0 se ignoran; no se puede recibir más de lo pendiente.
     * Solo el administrador puede corregir el costo de un renglón al recibirlo (factura distinta).
     */
    public function receive(User $user, PurchaseOrder $po, array $lines): PurchaseOrder
    {
        return DB::transaction(function () use ($user, $po, $lines) {
            $po = $this->lock($po);
            $this->requireStatus($po, ['enviada', 'parcial'], 'Esta orden no está esperando mercancía.');

            $byId     = $po->items()->with('item')->lockForUpdate()->get()->keyBy('id');
            $received = 0;
            $costFixed = false;

            foreach ($lines as $row) {
                $qty = (float) ($row['quantity'] ?? 0);
                if ($qty <= 0) continue;

                $line = $byId->get((int) ($row['line_id'] ?? 0));
                if (! $line) {
                    throw ValidationException::withMessages(['lines' => 'Hay un renglón que no pertenece a esta orden.']);
                }

                $pending = (float) $line->quantity_ordered - (float) $line->quantity_received;
                if ($qty > $pending + self::EPS) {
                    throw ValidationException::withMessages([
                        'lines' => sprintf('De "%s" solo faltan %s %s por recibir.', $line->item->name, $this->fmt($pending), $line->item->unit),
                    ]);
                }
                if (empty($row['location_id'])) {
                    throw ValidationException::withMessages(['lines' => "Indica en qué ubicación se guarda \"{$line->item->name}\"."]);
                }

                if ($user->isAdmin() && isset($row['unit_cost']) && $row['unit_cost'] !== '' && (float) $row['unit_cost'] !== (float) $line->unit_cost) {
                    $line->unit_cost = (float) $row['unit_cost'];
                    $costFixed = true;
                }

                $this->inventory->move(
                    $user, $line->item_id, (int) $row['location_id'], 'entrada', $qty,
                    (float) $line->unit_cost, 'Recepción ' . $po->code, 'purchase_order', $po->id,
                );

                $line->quantity_received = (float) $line->quantity_received + $qty;
                $line->save();
                $received++;
            }

            if ($received === 0) {
                throw ValidationException::withMessages(['lines' => 'Indica la cantidad recibida de al menos un artículo.']);
            }

            $complete = $byId->every(fn ($l) => (float) $l->quantity_received + self::EPS >= (float) $l->quantity_ordered);
            $po->status = $complete ? 'recibida' : 'parcial';
            if ($complete) $po->received_date = now()->toDateString();
            $po->save();

            if ($costFixed) $po->recalculateTotals();

            return $po;
        });
    }

    /**
     * El proveedor no enviará el resto: la orden se da por recibida con lo que llegó.
     * Los renglones se ajustan a lo recibido (y los que no llegaron se quitan) para que el total sea real.
     */
    public function closeShort(PurchaseOrder $po): PurchaseOrder
    {
        return DB::transaction(function () use ($po) {
            $po = $this->lock($po);
            $this->requireStatus($po, ['parcial'], 'Solo se puede dar por recibida una orden con entregas parciales.');

            $po->items()->where('quantity_received', '<=', 0)->delete();
            foreach ($po->items()->get() as $line) {
                $line->quantity_ordered = $line->quantity_received;
                $line->save();
            }

            $po->status        = 'recibida';
            $po->received_date = now()->toDateString();
            $po->save();
            $po->recalculateTotals();
            return $po;
        });
    }

    public function cancel(PurchaseOrder $po): PurchaseOrder
    {
        return DB::transaction(function () use ($po) {
            $po = $this->lock($po);
            $this->requireStatus(
                $po, ['borrador', 'enviada'],
                'Solo se cancela una orden que aún no ha recibido mercancía. Si ya llegó parte, usa "Dar por recibida".'
            );
            $po->status = 'cancelada';
            $po->save();
            return $po;
        });
    }

    public function delete(PurchaseOrder $po): void
    {
        DB::transaction(function () use ($po) {
            $po = $this->lock($po);
            $this->requireStatus($po, ['borrador'], 'Solo se pueden borrar órdenes en borrador (las demás se cancelan).');
            $po->delete();   // los renglones se borran en cascada
        });
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function replaceLines(PurchaseOrder $po, array $lines): void
    {
        $po->items()->delete();
        foreach ($lines as $l) {
            PurchaseOrderItem::create([
                'purchase_order_id' => $po->id,
                'item_id'           => $l['item_id'],
                'quantity_ordered'  => $l['quantity'],
                'quantity_received' => 0,
                'unit_cost'         => $l['unit_cost'],
            ]);
        }
    }

    private function lock(PurchaseOrder $po): PurchaseOrder
    {
        return PurchaseOrder::lockForUpdate()->findOrFail($po->id);
    }

    private function requireStatus(PurchaseOrder $po, array $allowed, string $message): void
    {
        if (! in_array($po->status, $allowed, true)) {
            throw ValidationException::withMessages(['order' => "{$message} (La orden {$po->code} está {$po->status}.)"]);
        }
    }

    private function fmt(float $n): string
    {
        return rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.') ?: '0';
    }
}

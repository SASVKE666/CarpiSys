<?php

namespace App\Services;

use App\Models\LaborRecord;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mano de obra por orden. Cada registro guarda la tarifa vigente al momento de registrarlo,
 * de modo que cambiar la tarifa de alguien después no altera los costos ya registrados.
 */
class CostingService
{
    /**
     * Registra horas trabajadas en una orden y recalcula MOD y CIF.
     *
     * - Operario: siempre se registra a sí mismo con su tarifa (la define el administrador).
     * - Administrador: puede indicar otra persona (worker_name) y su tarifa, para personal sin cuenta.
     */
    public function logLabor(
        User $user, WorkOrder $order, float $hours, ?string $date = null, ?string $task = null,
        ?string $workerName = null, ?float $rate = null,
    ): LaborRecord {
        if ($hours <= 0) {
            throw ValidationException::withMessages(['hours' => 'Las horas deben ser mayores que cero.']);
        }

        if ($user->isAdmin()) {
            $worker = trim((string) $workerName) !== '' ? trim((string) $workerName) : $user->name;
            $rate   = $rate ?? ($user->hourly_rate !== null ? (float) $user->hourly_rate : null);
        } else {
            $worker = $user->name;
            $rate   = $user->hourly_rate !== null ? (float) $user->hourly_rate : null;
        }

        if ($rate === null || $rate <= 0) {
            throw ValidationException::withMessages([
                'hours' => $user->isAdmin()
                    ? 'Indica la tarifa por hora (o define la tuya en Usuarios).'
                    : 'Tu tarifa por hora aún no está definida. Pídele al administrador que la capture en Usuarios.',
            ]);
        }

        return DB::transaction(function () use ($user, $order, $hours, $date, $task, $worker, $rate) {
            $order = WorkOrder::lockForUpdate()->findOrFail($order->id);
            if (! $order->isOpen()) {
                throw ValidationException::withMessages([
                    'order' => "La orden {$order->code} está {$order->status}: ya no admite cambios de materiales ni horas.",
                ]);
            }

            $record = LaborRecord::create([
                'work_order_id'    => $order->id,
                'user_id'          => $user->id,
                'worker_name'      => $worker,
                'work_date'        => $date ?: now()->toDateString(),
                'hours'            => $hours,
                'hourly_rate'      => $rate,
                'task_description' => $task,
            ]);

            $order->recalculateCosts();

            return $record;
        });
    }

    public function removeLabor(LaborRecord $record): void
    {
        DB::transaction(function () use ($record) {
            $order = WorkOrder::lockForUpdate()->findOrFail($record->work_order_id);
            if (! $order->isOpen()) {
                throw ValidationException::withMessages([
                    'order' => "La orden {$order->code} está {$order->status}: ya no admite cambios de materiales ni horas.",
                ]);
            }
            $record->delete();
            $order->recalculateCosts();
        });
    }
}

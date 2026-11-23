<?php

namespace App\Services;

use App\Models\InventoryMovement;

/**
 * Cuenta los movimientos de inventario por semana (de lunes a domingo, en la zona horaria
 * de la aplicación). Sirve para medir cuántas salidas de material quedan registradas en
 * CarpiSys cada semana. Solo son conteos: no incluye costos ni importes ni el nombre de
 * quien registró, para que el reporte no sirva para vigilar a una persona.
 */
class MovementReportService
{
    public const MAX_WEEKS = 52;

    /**
     * Una fila por semana, de la más antigua a la actual, incluidas las semanas sin movimientos.
     *
     * @return list<array{week:string,salidas:int,salidas_orden:int,salidas_manuales:int,mermas:int,devoluciones:int,entradas:int,ordenes:int,dias_con_salidas:int}>
     */
    public function weekly(int $weeks = 12): array
    {
        $weeks = max(1, min(self::MAX_WEEKS, $weeks));

        $thisMonday = now()->startOfWeek();
        $start      = $thisMonday->copy()->subWeeks($weeks - 1);

        $buckets = [];
        for ($i = 0; $i < $weeks; $i++) {
            $key = $start->copy()->addWeeks($i)->toDateString();
            $buckets[$key] = [
                'week'             => $key,
                'salidas'          => 0,
                'salidas_orden'    => 0,
                'salidas_manuales' => 0,
                'mermas'           => 0,
                'devoluciones'     => 0,
                'entradas'         => 0,
                'ordenes'          => 0,
                'dias_con_salidas' => 0,
            ];
        }

        $orders = [];   // semana => [id de orden => true]
        $days   = [];   // semana => [fecha => true]

        InventoryMovement::where('created_at', '>=', $start)
            ->orderBy('id')
            ->get(['id', 'movement_type', 'reference_type', 'reference_id', 'created_at'])
            ->each(function (InventoryMovement $m) use (&$buckets, &$orders, &$days) {
                $key = $m->created_at->copy()->startOfWeek()->toDateString();
                if (! isset($buckets[$key])) {
                    return;
                }

                switch ($m->movement_type) {
                    case 'salida':
                        $buckets[$key]['salidas']++;
                        $days[$key][$m->created_at->toDateString()] = true;
                        if ($m->reference_type === 'work_order' && $m->reference_id) {
                            $buckets[$key]['salidas_orden']++;
                            $orders[$key][$m->reference_id] = true;
                        } else {
                            $buckets[$key]['salidas_manuales']++;
                        }
                        break;
                    case 'merma':
                        $buckets[$key]['mermas']++;
                        break;
                    case 'devolucion':
                        $buckets[$key]['devoluciones']++;
                        break;
                    case 'entrada':
                        $buckets[$key]['entradas']++;
                        break;
                }
            });

        foreach ($buckets as $key => &$row) {
            $row['ordenes']          = count($orders[$key] ?? []);
            $row['dias_con_salidas'] = count($days[$key] ?? []);
        }
        unset($row);

        return array_values($buckets);
    }
}

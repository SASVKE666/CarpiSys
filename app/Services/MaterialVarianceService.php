<?php

namespace App\Services;

use App\Models\WorkOrder;
use Illuminate\Support\Collection;

/**
 * Compara el material estimado de una orden contra el que se registró como salida.
 *
 * Sirve para medir qué tanto del material que una orden necesitaba quedó registrado en el sistema.
 * Es una aproximación: si lo registrado es menos de lo estimado puede faltar registro de salidas o
 * haberse usado menos material; si es más, hubo desperdicio (merma) o un uso mayor al calculado.
 * Para distinguirlo hace falta la observación o el conteo físico del taller.
 *
 * Solo trabaja con cantidades, nunca con importes.
 */
class MaterialVarianceService
{
    /** Lo registrado cubre el estimado si llega al menos a este porcentaje (0.90 = 90 %). */
    public const COVERAGE_MIN = 0.90;
    /** Pasa a "exceso" cuando lo registrado supera el estimado por más de este margen (1.10 = +10 %). */
    public const EXCESS_MAX = 1.10;

    public const OK = 'ok';
    public const LOW = 'bajo';
    public const EXCESS = 'exceso';
    public const UNPLANNED = 'sin_estimado';

    /**
     * Una fila por artículo estimado o usado en la orden. Requiere materials.item y estimates.item cargados.
     *
     * @return list<array{estimate_id:?int,item_id:int,name:string,unit:string,estimated:float,used:float,diff:float,pct:?float,status:string}>
     */
    public function lines(WorkOrder $order): array
    {
        $rows = [];

        foreach ($order->estimates as $e) {
            $rows[$e->item_id] = [
                'estimate_id' => $e->id,
                'item_id'   => $e->item_id,
                'name'      => $e->item?->name ?? '—',
                'unit'      => $e->item?->unit ?? '',
                'estimated' => (float) $e->quantity,
                'used'      => 0.0,
            ];
        }

        foreach ($order->materials as $m) {
            $rows[$m->item_id] ??= [
                'estimate_id' => null,
                'item_id'   => $m->item_id,
                'name'      => $m->item?->name ?? '—',
                'unit'      => $m->item?->unit ?? '',
                'estimated' => 0.0,
                'used'      => 0.0,
            ];
            $rows[$m->item_id]['used'] += (float) $m->qty_actual;
        }

        return collect($rows)->map(function (array $r) {
            $r['used']   = round($r['used'], 4);
            $r['diff']   = round($r['used'] - $r['estimated'], 4);
            $r['pct']    = $r['estimated'] > 0 ? round($r['diff'] / $r['estimated'] * 100, 1) : null;
            $r['status'] = $this->status($r['estimated'], $r['used']);
            return $r;
        })->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }

    public function status(float $estimated, float $used): string
    {
        if ($estimated <= 0) {
            return self::UNPLANNED;
        }
        if ($used < $estimated * self::COVERAGE_MIN - 0.00001) {
            return self::LOW;
        }
        if ($used > $estimated * self::EXCESS_MAX + 0.00001) {
            return self::EXCESS;
        }
        return self::OK;
    }

    /**
     * Resumen de las órdenes completadas que tienen material estimado.
     * "cobertura" = % de líneas estimadas cuyo registro llegó al menos al 90 % de lo estimado.
     *
     * @param  Collection<int,WorkOrder>  $orders  con materials.item y estimates.item cargados
     * @return array{orders:list<array<string,mixed>>,total:array<string,mixed>}
     */
    public function summary(Collection $orders): array
    {
        $rows = [];
        foreach ($orders->where('status', 'completada') as $o) {
            if ($o->estimates->isEmpty()) {
                continue;
            }
            $planned = collect($this->lines($o))->where('status', '!=', self::UNPLANNED);
            $count   = fn (string $s) => $planned->where('status', $s)->count();
            $n       = $planned->count();

            $rows[] = [
                'id'        => $o->id,
                'code'      => $o->code,
                'lines'     => $n,
                'ok'        => $count(self::OK),
                'low'       => $count(self::LOW),
                'excess'    => $count(self::EXCESS),
                'unplanned' => collect($this->lines($o))->where('status', self::UNPLANNED)->count(),
                'coverage'  => $n > 0 ? round(($n - $count(self::LOW)) / $n * 100, 1) : null,
            ];
        }

        $lines = array_sum(array_column($rows, 'lines'));
        $low   = array_sum(array_column($rows, 'low'));

        return [
            'orders' => $rows,
            'total'  => [
                'orders'   => count($rows),
                'lines'    => $lines,
                'ok'       => array_sum(array_column($rows, 'ok')),
                'low'      => $low,
                'excess'   => array_sum(array_column($rows, 'excess')),
                'coverage' => $lines > 0 ? round(($lines - $low) / $lines * 100, 1) : null,
            ],
        ];
    }
}

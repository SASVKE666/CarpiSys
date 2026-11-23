<?php

namespace App\Http\Controllers;

use App\Services\MovementReportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /** Descarga el conteo semanal de movimientos como CSV (abre bien en Excel). */
    public function weeklyCsv(Request $request, MovementReportService $report): StreamedResponse
    {
        $weeks = $request->integer('semanas', 12);
        $rows  = $report->weekly($weeks);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // BOM para que Excel lea los acentos
            fputcsv($out, [
                'Semana (lunes)', 'Salidas', 'Salidas con orden', 'Salidas manuales',
                'Mermas', 'Devoluciones', 'Entradas', 'Órdenes con salidas', 'Días con salidas',
            ]);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['week'], $r['salidas'], $r['salidas_orden'], $r['salidas_manuales'],
                    $r['mermas'], $r['devoluciones'], $r['entradas'], $r['ordenes'], $r['dias_con_salidas'],
                ]);
            }
            fclose($out);
        }, 'movimientos-semanales.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\MonthlyReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * «Descargar PDF del mes»: lo que muestran Informes y Gráficos de un mes, en un
 * archivo. Los gráficos van en HTML/CSS porque dompdf no ejecuta Chart.js.
 */
class MonthlyPdfController extends Controller
{
    public function __invoke(Request $request, MonthlyReport $report): Response
    {
        $user = $request->user();
        $data = $report->build($user, MonthlyReport::monthFromQuery($request->query('month')));

        $pdf = Pdf::loadView('pdf.monthly', [
            ...$data,
            'userName' => $user->name,
            'generatedAt' => now()->translatedFormat('j \d\e F \d\e Y, H:i'),
        ])->setPaper('a4')
            // Solo los caracteres usados: con la fuente entera el PDF pesa ~850 KB.
            ->setOption('isFontSubsettingEnabled', true);

        $response = $pdf->download("mi-billetera-{$data['month']}.pdf");
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}

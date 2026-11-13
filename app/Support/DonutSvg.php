<?php

namespace App\Support;

/**
 * Tortas (donas) para el PDF del mes. dompdf no ejecuta Chart.js, así que se
 * dibujan acá como SVG y entran al PDF como imagen. Mismo aspecto que las de
 * Gráficos e Informes (resources/js/Components/DoughnutChart.vue).
 */
class DonutSvg
{
    private const OUTER = 95;

    private const INNER = 59; // agujero del 62 %, como en la página

    /**
     * Porciones [{name, total, color}] → data URI de un SVG listo para <img>.
     */
    public static function dataUri(array $slices): string
    {
        $slices = array_values(array_filter($slices, fn ($s) => $s['total'] > 0));
        $sum = array_sum(array_column($slices, 'total'));

        $paths = '';
        $start = 0.0;
        foreach ($slices as $slice) {
            $sweep = $sum > 0 ? $slice['total'] / $sum * 360 : 0;
            // Un arco de 360° no se puede dibujar (empieza y termina en el mismo
            // punto): una porción que ocupa todo va en dos mitades.
            $parts = $sweep >= 359.99 ? [[$start, $start + 180], [$start + 180, $start + 360]] : [[$start, $start + $sweep]];
            foreach ($parts as [$a0, $a1]) {
                $paths .= '<path d="'.self::sector($a0, $a1).'" fill="'.e($slice['color']).'"/>';
            }
            $start += $sweep;
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">'.$paths.'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * El color de cada gasto fijo, igual que fixedColor() de resources/js/chartColors.js
     * (hsl(id × 137,508°, 68 %, 55 %)), pero en hex porque el SVG de dompdf no lee hsl().
     */
    public static function fixedColor(int $id): string
    {
        return self::hslToHex(fmod(round($id * 137.508), 360), 0.68, 0.55);
    }

    // Sector de corona entre dos ángulos (0° arriba, sentido horario).
    private static function sector(float $a0, float $a1): string
    {
        $large = $a1 - $a0 > 180 ? 1 : 0;
        [$x0, $y0] = self::point(self::OUTER, $a0);
        [$x1, $y1] = self::point(self::OUTER, $a1);
        [$x2, $y2] = self::point(self::INNER, $a1);
        [$x3, $y3] = self::point(self::INNER, $a0);

        return sprintf(
            'M%.2f %.2f A%d %d 0 %d 1 %.2f %.2f L%.2f %.2f A%d %d 0 %d 0 %.2f %.2f Z',
            $x0, $y0, self::OUTER, self::OUTER, $large, $x1, $y1,
            $x2, $y2, self::INNER, self::INNER, $large, $x3, $y3,
        );
    }

    private static function point(int $radius, float $deg): array
    {
        $rad = deg2rad($deg - 90);

        return [100 + $radius * cos($rad), 100 + $radius * sin($rad)];
    }

    private static function hslToHex(float $h, float $s, float $l): string
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0],
            $h < 120 => [$x, $c, 0],
            $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c],
            $h < 300 => [$x, 0, $c],
            default => [$c, 0, $x],
        };

        return sprintf('#%02x%02x%02x', round(($r + $m) * 255), round(($g + $m) * 255), round(($b + $m) * 255));
    }
}

<?php

namespace Tests\Unit;

use App\Support\DonutSvg;
use PHPUnit\Framework\TestCase;

class DonutSvgTest extends TestCase
{
    private function svg(array $slices): string
    {
        return base64_decode(substr(DonutSvg::dataUri($slices), strlen('data:image/svg+xml;base64,')));
    }

    public function test_one_path_per_slice_and_empty_slices_are_skipped(): void
    {
        $svg = $this->svg([
            ['name' => 'A', 'total' => 300, 'color' => '#ff0000'],
            ['name' => 'B', 'total' => 100, 'color' => '#00ff00'],
            ['name' => 'C', 'total' => 0, 'color' => '#0000ff'],
        ]);

        $this->assertSame(2, substr_count($svg, '<path'));
        $this->assertStringNotContainsString('#0000ff', $svg);
        // La de 3/4 pasa de 180°: arco largo.
        $this->assertStringContainsString('0 1 1', $svg);
    }

    public function test_a_single_slice_is_drawn_in_two_halves(): void
    {
        $svg = $this->svg([['name' => 'Todo', 'total' => 500, 'color' => '#123456']]);

        $this->assertSame(2, substr_count($svg, 'fill="#123456"'));
    }

    public function test_fixed_colors_match_the_web(): void
    {
        // chartColors.js: hsl(round(id × 137,508 mod 360), 68 %, 55 %).
        $this->assertSame('#3eda6d', DonutSvg::fixedColor(1)); // hsl(138°, 68%, 55%)
        $this->assertSame('#da3e3e', DonutSvg::fixedColor(0)); // hsl(0°, 68%, 55%)
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', DonutSvg::fixedColor(42));
    }
}

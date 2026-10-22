<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MonthlyReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MonthlyPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-21 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function txn(User $user, string $type, float $amount, string $date, $category = null): void
    {
        $user->transactions()->create([
            'account_id' => $user->accounts()->first()->id,
            'category_id' => ($category ?? $user->categories()->where('type', $type)->first())->id,
            'type' => $type,
            'amount' => $amount,
            'currency_code' => 'ARS',
            'exchange_rate' => 1,
            'transaction_date' => $date,
        ]);
    }

    public function test_downloads_the_month_as_pdf(): void
    {
        $user = User::factory()->create();
        $this->txn($user, 'expense', 500, '2026-08-10');
        $this->txn($user, 'income', 2000, '2026-08-01');

        $response = $this->actingAs($user)->get('/informes/pdf?month=2026-08');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename=mi-billetera-2026-08.pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/informes/pdf')->assertRedirect('/login');
    }

    public function test_invalid_or_future_month_falls_back_to_current(): void
    {
        $user = User::factory()->create();

        foreach (['basura', '2026-13', '2027-01'] as $month) {
            $this->actingAs($user)->get('/informes/pdf?month='.$month)
                ->assertOk()
                ->assertHeader('Content-Disposition', 'attachment; filename=mi-billetera-2026-10.pdf');
        }

        $this->actingAs($user)->get('/reports?month=basura')->assertOk();
    }

    public function test_report_totals_breakdowns_and_days(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        [$food, $car] = $user->categories()->where('type', 'expense')->take(2)->get()->all();

        $super = $user->supercategories()->create(['name' => 'Comida', 'color' => '#ff0000']);
        $food->update(['supercategory_id' => $super->id]);

        $this->txn($user, 'expense', 300, '2026-08-10', $food);
        $this->txn($user, 'expense', 100, '2026-08-10', $car);
        $this->txn($user, 'income', 1000, '2026-08-01');
        $this->txn($user, 'expense', 200, '2026-07-05');
        $this->txn($other, 'expense', 9999, '2026-08-10');

        $data = app(MonthlyReport::class)->build($user, Carbon::parse('2026-08-01'));

        $this->assertSame('Agosto 2026', $data['label']);
        $this->assertSame(1000.0, $data['totals']['income']);
        $this->assertSame(400.0, $data['totals']['expense']);
        $this->assertSame(600.0, $data['totals']['balance']);
        $this->assertSame(100.0, $data['totals']['expenseDelta']);

        $this->assertSame(75.0, $data['expenseByCategory'][0]['pct']);
        $this->assertSame('Comida', $data['bySupercategory'][0]['name']);
        $this->assertSame(75.0, $data['bySupercategory'][0]['pct']);
        $this->assertSame('Sin supercategoría', $data['bySupercategory'][1]['name']);

        $this->assertCount(31, $data['days']);
        $this->assertSame(400.0, $data['days'][9]['expense']);
    }
}

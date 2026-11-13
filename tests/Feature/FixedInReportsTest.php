<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use App\Services\MonthlyReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Los gastos fijos en Gráficos, Informes y el PDF del mes: cuánto suman, qué
 * parte del gasto son, qué está pagado y qué falta, y uno por uno.
 */
class FixedInReportsTest extends TestCase
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

    private function load(User $user, array $overrides = []): void
    {
        $this->actingAs($user)->post('/transactions', [
            'type' => 'expense',
            'amount' => 1000,
            'account_id' => $user->accounts()->first()->id,
            'category_id' => $user->categories()->where('type', 'expense')->first()->id,
            'transaction_date' => now()->toDateString(),
            ...$overrides,
        ])->assertSessionHasNoErrors();
    }

    /**
     * Netflix fijo desde agosto (1.000 por mes: agosto lo cargó el usuario y está
     * pago; septiembre y octubre los generó el sistema, impagos), un alquiler fijo
     * desde octubre (5.000, pago), un gasto común y una compra en cuotas en octubre.
     */
    private function scenario(): User
    {
        $user = User::factory()->create();
        $card = $user->accounts()->create([
            'name' => 'Visa', 'type' => 'card', 'currency_code' => 'ARS',
            'initial_balance' => 0, 'accepts_installments' => true,
        ]);

        $this->load($user, ['is_fixed' => 1, 'note' => 'Netflix', 'transaction_date' => '2026-08-06']);
        $this->load($user, ['is_fixed' => 1, 'note' => 'Alquiler', 'amount' => 5000, 'transaction_date' => '2026-10-05']);
        $this->load($user, ['note' => 'Supermercado', 'amount' => 3000, 'transaction_date' => '2026-10-10']);
        $this->load($user, ['note' => 'Heladera', 'amount' => 3000, 'account_id' => $card->id, 'installments' => 3, 'transaction_date' => '2026-10-12']);

        return $user;
    }

    private function netflix(User $user, string $month): Transaction
    {
        return $user->transactions()->where('note', 'Netflix')
            ->where('transaction_date', 'like', $month.'%')->sole();
    }

    public function test_the_month_report_separates_fixed_expenses(): void
    {
        $user = $this->scenario();
        // La nota de una repetición suelta no cambia el nombre del fijo: manda la de la serie.
        $this->netflix($user, '2026-10')->update(['note' => 'Netflix de octubre']);

        $fixed = app(MonthlyReport::class)->build($user, Carbon::parse('2026-10-01'))['fixed'];

        // Gasto de octubre: 1.000 + 5.000 fijos, 3.000 del súper y 1.000 de la cuota 1.
        $this->assertSame(6000.0, $fixed['total']);
        $this->assertSame(4000.0, $fixed['variable']);
        $this->assertSame(60.0, $fixed['pct']);
        $this->assertSame(5000.0, $fixed['paid']);
        $this->assertSame(1000.0, $fixed['pending']);

        // Septiembre: solo Netflix.
        $this->assertSame(1000.0, $fixed['prevTotal']);
        $this->assertSame(500.0, $fixed['delta']);

        $this->assertSame(['Alquiler', 'Netflix'], array_column($fixed['items'], 'name'));
        [$rent, $netflix] = $fixed['items'];
        $this->assertTrue($rent['isPaid']);
        $this->assertFalse($netflix['isPaid']);
        $this->assertSame('2026-10-06', $netflix['date']);
        $this->assertTrue($netflix['active']);
    }

    public function test_marking_paid_and_cutting_the_series_are_reflected(): void
    {
        $user = $this->scenario();
        $october = $this->netflix($user, '2026-10');

        $this->actingAs($user)->patch("/transactions/{$october->id}/paid", ['is_paid' => true]);
        // Destildar «Gasto fijo» en la de octubre: es la última, pero sigue contando.
        $this->actingAs($user)->post("/transactions/{$october->id}", [
            'type' => 'expense', 'amount' => 1000, 'note' => 'Netflix', 'is_fixed' => 0,
            'account_id' => $october->account_id, 'category_id' => $october->category_id,
            'transaction_date' => '2026-10-06',
        ])->assertSessionHasNoErrors();

        $fixed = app(MonthlyReport::class)->build($user, Carbon::parse('2026-10-01'))['fixed'];
        $netflix = collect($fixed['items'])->firstWhere('name', 'Netflix');

        $this->assertSame(0.0, $fixed['pending']);
        $this->assertTrue($netflix['isPaid']);
        $this->assertFalse($netflix['active']);
    }

    public function test_reports_page_receives_the_fixed_expenses(): void
    {
        $user = $this->scenario();

        $this->actingAs($user)->get('/reports?month=2026-10')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Index')
                ->where('fixed.total', 6000)
                ->where('fixed.pending', 1000)
                ->has('fixed.items', 2)
                ->where('hasSupercategories', false)
                ->where('bySupercategory', []));
    }

    public function test_charts_show_the_period_and_the_year_split(): void
    {
        $user = $this->scenario();

        $this->actingAs($user)->get('/charts?range=month')
            ->assertInertia(fn (Assert $page) => $page
                ->where('fixed.total', 6000)
                ->where('fixed.pct', 60)
                ->has('fixed.items', 2)
                ->where('yearly.fixed.7', 1000)       // agosto
                ->where('yearly.variable.7', 0)
                ->where('yearly.fixed.9', 6000)       // octubre
                ->where('yearly.variable.9', 4000)
                ->where('yearly.fixed.0', 0)
                ->where('yearly.fixed.10', null));    // noviembre todavía no llegó

        // En el año, cada fijo una vez con los meses que se pagó y lo que falta.
        $this->actingAs($user)->get('/charts?range=year')
            ->assertInertia(fn (Assert $page) => $page
                ->where('fixed.total', 8000)
                ->where('fixed.items.0.name', 'Alquiler')
                ->where('fixed.items.1.name', 'Netflix')
                ->where('fixed.items.1.count', 3)
                ->where('fixed.items.1.total', 3000)
                ->where('fixed.items.1.pending', 2000));
    }

    public function test_without_fixed_expenses_everything_is_zero(): void
    {
        $user = User::factory()->create();
        $this->load($user, ['amount' => 700]);

        $fixed = app(MonthlyReport::class)->build($user, Carbon::parse('2026-10-01'))['fixed'];

        $this->assertSame([], $fixed['items']);
        $this->assertSame(0.0, $fixed['total']);
        $this->assertSame(700.0, $fixed['variable']);
        $this->assertEquals(0, $fixed['pct']);
        $this->assertNull($fixed['delta']);
    }

    public function test_the_pdf_includes_the_fixed_expenses(): void
    {
        $user = $this->scenario();

        $this->actingAs($user)->get('/informes/pdf?month=2026-10')->assertOk();
        $this->actingAs($user)->get('/informes/pdf?month=2026-01')->assertOk();
    }

    public function test_someone_elses_fixed_expenses_do_not_show(): void
    {
        $this->scenario();
        $other = User::factory()->create();

        $fixed = app(MonthlyReport::class)->build($other, Carbon::parse('2026-10-01'))['fixed'];

        $this->assertSame([], $fixed['items']);
        $this->assertSame(0.0, $fixed['total']);
    }
}

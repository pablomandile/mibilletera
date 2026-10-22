<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChartsPeriodTest extends TestCase
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

    private function expense(User $user, float $amount, string $date): void
    {
        $user->transactions()->create([
            'account_id' => $user->accounts()->first()->id,
            'category_id' => $user->categories()->where('type', 'expense')->first()->id,
            'type' => 'expense',
            'amount' => $amount,
            'currency_code' => 'ARS',
            'exchange_rate' => 1,
            'transaction_date' => $date,
        ]);
    }

    public function test_shows_a_past_month_with_its_own_totals(): void
    {
        $user = User::factory()->create();
        $this->expense($user, 500, '2026-08-10');
        $this->expense($user, 900, '2026-10-05');

        $this->actingAs($user)
            ->get('/charts?range=month&date=2026-08-01')
            ->assertInertia(fn (Assert $page) => $page
                ->where('periodLabel', 'Agosto 2026')
                ->where('totals.expense', 500)
                ->where('period.prev', '2026-07-01')
                ->where('period.next', '2026-09-01')
                ->where('period.isCurrent', false)
                ->has('trend.labels', 31));
    }

    public function test_without_date_shows_current_month_and_blocks_next(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/charts?range=month')
            ->assertInertia(fn (Assert $page) => $page
                ->where('period.date', '2026-10-01')
                ->where('period.prev', '2026-09-01')
                ->where('period.isCurrent', true));
    }

    public function test_future_or_invalid_date_falls_back_to_current_period(): void
    {
        $user = User::factory()->create();

        foreach (['2027-01-01', '2026-02-31', 'basura'] as $date) {
            $this->actingAs($user)
                ->get('/charts?range=month&date='.$date)
                ->assertInertia(fn (Assert $page) => $page->where('period.date', '2026-10-01'));
        }
    }

    public function test_arrows_move_by_week_and_by_year(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/charts?range=week&date=2026-10-07')
            ->assertInertia(fn (Assert $page) => $page
                ->where('period.date', '2026-10-05')
                ->where('period.prev', '2026-09-28')
                ->where('period.next', '2026-10-12'));

        $this->actingAs($user)
            ->get('/charts?range=year&date=2025-03-01')
            ->assertInertia(fn (Assert $page) => $page
                ->where('periodLabel', '2025')
                ->where('period.prev', '2024-01-01')
                ->where('period.isCurrent', false));
    }

    public function test_yearly_trend_compares_months_with_previous_year(): void
    {
        $user = User::factory()->create();
        $this->expense($user, 300, '2026-03-10');
        $this->expense($user, 200, '2026-03-25');
        $this->expense($user, 700, '2025-03-05');
        $this->expense($user, 50, '2024-03-05');

        $this->actingAs($user)
            ->get('/charts?range=month&date=2026-03-01')
            ->assertInertia(fn (Assert $page) => $page
                ->where('yearly.year', 2026)
                ->has('yearly.labels', 12)
                ->where('yearly.current.2', 500)
                ->where('yearly.previous.2', 700)
                ->where('yearly.current.0', 0)
                // Hoy es 21/10/2026: noviembre todavía no llegó.
                ->where('yearly.current.9', 0)
                ->where('yearly.current.10', null));
    }

    public function test_savings_accumulate_only_positive_monthly_balances(): void
    {
        $user = User::factory()->create();
        $income = fn (float $amount, string $date) => $user->transactions()->create([
            'account_id' => $user->accounts()->first()->id,
            'category_id' => $user->categories()->where('type', 'income')->first()->id,
            'type' => 'income',
            'amount' => $amount,
            'currency_code' => 'ARS',
            'exchange_rate' => 1,
            'transaction_date' => $date,
        ]);

        $income(1000, '2026-01-05');
        $this->expense($user, 400, '2026-01-20');   // enero ahorra 600
        $income(100, '2026-02-05');
        $this->expense($user, 300, '2026-02-20');   // febrero en rojo: 0
        $income(500, '2026-03-05');                 // marzo ahorra 500

        $this->actingAs($user)
            ->get('/charts?range=year&date=2026-01-01')
            ->assertInertia(fn (Assert $page) => $page
                ->where('savings.monthly.0', 600)
                ->where('savings.monthly.1', 0)
                ->where('savings.monthly.2', 500)
                ->where('savings.accumulated.0', 600)
                ->where('savings.accumulated.1', 600)
                ->where('savings.accumulated.2', 1100)
                ->where('savings.accumulated.9', 1100)
                ->where('savings.accumulated.10', null));
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-21 12:00:00');
        $this->seed([CurrencySeeder::class, DemoSeeder::class]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function demo(): User
    {
        return User::where('email', 'demo@pablomandile.com.ar')->firstOrFail();
    }

    public function test_creates_the_demo_user_with_its_password(): void
    {
        $user = $this->demo();

        $this->assertTrue(Hash::check('demo1234', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(config('legales.version'), $user->terminos_version);
        $this->assertSame(1, User::count());
        $this->assertSame(4, $user->accounts()->count());
    }

    public function test_a_year_of_movements_and_none_in_the_future(): void
    {
        $txns = $this->demo()->transactions()->get();

        $this->assertGreaterThan(300, $txns->count());
        $this->assertTrue($txns->every(fn ($t) => $t->transaction_date->lte(now())));

        $months = $txns->map(fn ($t) => $t->transaction_date->format('Y-m'))->unique();
        $this->assertCount(13, $months); // 12 meses completos + el actual
        $this->assertTrue($txns->contains(fn ($t) => $t->type === 'transfer'));
        $this->assertTrue($txns->contains(fn ($t) => $t->currency_code === 'USD' && $t->type === 'expense'));
    }

    public function test_most_months_save_and_some_end_in_red(): void
    {
        $balances = $this->demo()->transactions()->whereIn('type', ['income', 'expense'])->get()
            ->groupBy(fn ($t) => $t->transaction_date->format('Y-m'))
            ->map(fn ($g) => $g->where('type', 'income')->sum('base_amount') - $g->where('type', 'expense')->sum('base_amount'));

        // El mes en curso está a medias: no cuenta.
        $complete = $balances->except(now()->format('Y-m'));

        $this->assertGreaterThanOrEqual(8, $complete->filter(fn ($b) => $b > 0)->count());
        $this->assertGreaterThanOrEqual(1, $complete->filter(fn ($b) => $b < 0)->count());
    }

    public function test_extras_to_show_off_the_app(): void
    {
        $user = $this->demo();

        $this->assertSame(5, $user->supercategories()->count());
        $this->assertTrue($user->budgets()->whereNull('category_id')->exists());
        $this->assertGreaterThanOrEqual(5, $user->budgets()->count());
        $this->assertSame(5, $user->recurringTransactions()->count());
        $this->assertTrue($user->recurringTransactions()->get()->every(fn ($r) => $r->next_run_date->gt(now())));
        $this->assertSame(1, $user->alerts()->whereNull('read_at')->count());
    }

    public function test_the_demo_user_can_see_charts_and_download_the_pdf(): void
    {
        $this->actingAs($this->demo())->get('/charts')->assertOk();
        $this->actingAs($this->demo())->get('/informes/pdf')->assertOk();
    }
}

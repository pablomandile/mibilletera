<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Gasto fijo (se repite cada mes el mismo día) y compras en cuotas en las
 * cuentas que las aceptan.
 */
class FixedAndInstallmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function expense(User $user, array $overrides = []): array
    {
        return [
            'type' => 'expense',
            'amount' => 1000,
            'account_id' => $user->accounts()->first()->id,
            'category_id' => $user->categories()->where('type', 'expense')->first()->id,
            'transaction_date' => now()->toDateString(),
            'note' => 'Netflix',
            ...$overrides,
        ];
    }

    private function card(User $user, bool $accepts = true): Account
    {
        return $user->accounts()->create([
            'name' => 'Visa', 'type' => 'card', 'currency_code' => 'ARS',
            'initial_balance' => 0, 'accepts_installments' => $accepts,
        ]);
    }

    private function dates(User $user): array
    {
        return $user->transactions()->orderBy('transaction_date')->get()
            ->map(fn (Transaction $t) => $t->transaction_date->toDateString())->all();
    }

    public function test_fixed_expense_repeats_monthly_on_the_same_day(): void
    {
        Carbon::setTestNow('2026-01-31 10:00');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/transactions', $this->expense($user, ['is_fixed' => 1]))->assertRedirect();

        $series = RecurringTransaction::sole();
        $this->assertSame('fixed', $series->kind);
        $this->assertSame('2026-02-28', $series->next_run_date->toDateString());
        $this->assertTrue($user->transactions()->first()->isFixed());

        Carbon::setTestNow('2026-03-31 10:00');
        $this->artisan('recurring:run')->assertSuccessful();

        // Febrero no tiene 31, pero marzo vuelve al 31: no se corre.
        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31'], $this->dates($user));
        $this->assertSame(3, $series->transactions()->count());
    }

    public function test_a_fixed_expense_loaded_with_an_old_date_catches_up_right_away(): void
    {
        Carbon::setTestNow('2026-05-20 10:00');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/transactions', $this->expense($user, [
            'is_fixed' => 1, 'transaction_date' => '2026-03-10',
        ]));

        $this->assertSame(['2026-03-10', '2026-04-10', '2026-05-10'], $this->dates($user));
    }

    public function test_unchecking_fixed_on_edit_makes_it_the_last_one(): void
    {
        Carbon::setTestNow('2026-01-10 10:00');
        $user = User::factory()->create();
        $this->actingAs($user)->post('/transactions', $this->expense($user, ['is_fixed' => 1]));

        Carbon::setTestNow('2026-02-10 10:00');
        $this->artisan('recurring:run');
        $february = $user->transactions()->latest('transaction_date')->first();

        $this->actingAs($user)->post("/transactions/{$february->id}", $this->expense($user, [
            'transaction_date' => '2026-02-10', 'is_fixed' => 0,
        ]))->assertRedirect();

        $this->assertFalse(RecurringTransaction::sole()->is_active);
        Carbon::setTestNow('2026-04-10 10:00');
        $this->artisan('recurring:run');
        $this->assertSame(2, $user->transactions()->count());
    }

    public function test_editing_a_fixed_expense_carries_the_change_forward(): void
    {
        Carbon::setTestNow('2026-01-10 10:00');
        $user = User::factory()->create();
        $this->actingAs($user)->post('/transactions', $this->expense($user, ['is_fixed' => 1]));
        $first = $user->transactions()->first();

        $this->actingAs($user)->post("/transactions/{$first->id}", $this->expense($user, [
            'amount' => 1500, 'is_fixed' => 1,
        ]));

        Carbon::setTestNow('2026-02-10 10:00');
        $this->artisan('recurring:run');

        $this->assertEquals(1500, $user->transactions()->latest('transaction_date')->first()->amount);
        $this->assertTrue(RecurringTransaction::sole()->is_active);
    }

    public function test_marking_an_existing_expense_as_fixed_starts_a_series(): void
    {
        Carbon::setTestNow('2026-01-10 10:00');
        $user = User::factory()->create();
        $this->actingAs($user)->post('/transactions', $this->expense($user));
        $tx = $user->transactions()->first();
        $this->assertNull($tx->recurring_transaction_id);

        $this->actingAs($user)->post("/transactions/{$tx->id}", $this->expense($user, ['is_fixed' => 1]));

        $this->assertSame(RecurringTransaction::sole()->id, $tx->fresh()->recurring_transaction_id);
    }

    public function test_marking_last_months_expense_as_fixed_repeats_it_this_month(): void
    {
        Carbon::setTestNow('2026-11-04 10:00');
        $user = User::factory()->create();
        $this->actingAs($user)->post('/transactions', $this->expense($user, ['transaction_date' => '2026-10-02']));
        $tx = $user->transactions()->first();

        $this->actingAs($user)->post("/transactions/{$tx->id}", $this->expense($user, [
            'transaction_date' => '2026-10-02', 'is_fixed' => 1,
        ]))->assertSessionHasNoErrors();

        $this->assertSame(['2026-10-02', '2026-11-02'], $this->dates($user));
    }

    // Gasto del 8/10 marcado como fijo el 4/11: la del 8/11 aparece ya, sin esperar al 8.
    private function fixedOnTheEighth(): User
    {
        Carbon::setTestNow('2026-11-04 10:00');
        $user = User::factory()->create();
        $this->actingAs($user)->post('/transactions', $this->expense($user, ['transaction_date' => '2026-10-08']));
        $tx = $user->transactions()->first();
        $this->actingAs($user)->post("/transactions/{$tx->id}", $this->expense($user, [
            'transaction_date' => '2026-10-08', 'is_fixed' => 1,
        ]));

        return $user;
    }

    public function test_this_months_repetition_appears_right_away_even_if_its_day_has_not_come(): void
    {
        $user = $this->fixedOnTheEighth();

        $this->assertSame(['2026-10-08', '2026-11-08'], $this->dates($user));
        $this->assertSame('2026-12-08', RecurringTransaction::sole()->next_run_date->toDateString());

        // El 1° de diciembre el proceso diario genera la de diciembre.
        Carbon::setTestNow('2026-12-01 00:05');
        $this->artisan('recurring:run');
        $this->assertSame(['2026-10-08', '2026-11-08', '2026-12-08'], $this->dates($user));
    }

    public function test_a_new_fixed_expense_shows_this_months_occurrence_only_once(): void
    {
        Carbon::setTestNow('2026-11-04 10:00');
        $user = User::factory()->create();
        $this->actingAs($user)->post('/transactions', $this->expense($user, ['is_fixed' => 1, 'transaction_date' => '2026-11-20']));

        // La del mes es la que se cargó; la próxima es la de diciembre.
        $this->assertSame(['2026-11-20'], $this->dates($user));
        $this->artisan('recurring:run');
        $this->assertSame(['2026-11-20'], $this->dates($user));
    }

    public function test_editing_a_fixed_expense_redoes_the_upcoming_repetition(): void
    {
        $user = $this->fixedOnTheEighth();
        $october = $user->transactions()->orderBy('transaction_date')->first();

        $this->actingAs($user)->post("/transactions/{$october->id}", $this->expense($user, [
            'transaction_date' => '2026-10-08', 'is_fixed' => 1, 'amount' => 1800,
        ]));

        $this->assertSame(['2026-10-08', '2026-11-08'], $this->dates($user));
        $this->assertEquals(1800, $user->transactions()->whereDate('transaction_date', '2026-11-08')->sole()->amount);
    }

    public function test_unchecking_fixed_removes_the_upcoming_repetition(): void
    {
        $user = $this->fixedOnTheEighth();
        $october = $user->transactions()->orderBy('transaction_date')->first();

        $this->actingAs($user)->post("/transactions/{$october->id}", $this->expense($user, [
            'transaction_date' => '2026-10-08', 'is_fixed' => 0,
        ]));

        $this->assertSame(['2026-10-08'], $this->dates($user));
        $this->assertFalse(RecurringTransaction::sole()->is_active);
    }

    public function test_deleting_the_origin_with_only_the_upcoming_repetition_removes_the_series(): void
    {
        $user = $this->fixedOnTheEighth();
        // La de octubre ya pasó: borrar la de noviembre (adelantada) no borra la serie.
        $this->actingAs($user)->delete('/transactions/'.$user->transactions()->whereDate('transaction_date', '2026-11-08')->sole()->id);
        $this->assertSame(1, RecurringTransaction::count());

        // Un fijo cargado por error con fecha futura: borrarlo limpia la serie.
        $other = User::factory()->create();
        $this->actingAs($other)->post('/transactions', $this->expense($other, ['is_fixed' => 1, 'transaction_date' => '2026-11-25']));
        $this->actingAs($other)->delete('/transactions/'.$other->transactions()->sole()->id);
        $this->assertSame(0, $other->recurringTransactions()->count());
    }

    public function test_installments_split_the_total_and_number_each_one(): void
    {
        Carbon::setTestNow('2026-01-15 10:00');
        $user = User::factory()->create();
        $card = $this->card($user);

        $this->actingAs($user)->post('/transactions', $this->expense($user, [
            'account_id' => $card->id, 'amount' => 100000, 'installments' => 3,
        ]))->assertRedirect()->assertSessionHasNoErrors();

        Carbon::setTestNow('2026-06-15 10:00');
        $this->artisan('recurring:run');

        $rows = $user->transactions()->orderBy('transaction_date')->get();
        $this->assertSame(['2026-01-15', '2026-02-15', '2026-03-15'], $this->dates($user));
        $this->assertSame(['33333.34', '33333.33', '33333.33'], $rows->pluck('amount')->all());
        $this->assertSame([1, 2, 3], $rows->pluck('installment_number')->all());
        $this->assertSame([3, 3, 3], $rows->pluck('installments_total')->all());
        $this->assertFalse(RecurringTransaction::sole()->is_active);
    }

    public function test_installments_are_rejected_when_they_do_not_apply(): void
    {
        $user = User::factory()->create();
        $noCard = $this->card($user, false);
        $card = $this->card($user);
        $income = $user->categories()->where('type', 'income')->first();

        $this->actingAs($user)->post('/transactions', $this->expense($user, ['account_id' => $noCard->id, 'installments' => 3]))
            ->assertSessionHasErrors('installments');
        $this->actingAs($user)->post('/transactions', $this->expense($user, [
            'account_id' => $card->id, 'installments' => 3, 'type' => 'income', 'category_id' => $income->id,
        ]))->assertSessionHasErrors('installments');
        $this->actingAs($user)->post('/transactions', $this->expense($user, ['account_id' => $card->id, 'installments' => 3, 'is_fixed' => 1]))
            ->assertSessionHasErrors('installments');

        $this->assertSame(0, $user->transactions()->count());
        $this->assertSame(0, RecurringTransaction::count());
    }

    public function test_deleting_the_origin_before_it_repeats_removes_the_series(): void
    {
        $user = User::factory()->create();
        $card = $this->card($user);
        $this->actingAs($user)->post('/transactions', $this->expense($user, ['account_id' => $card->id, 'installments' => 6]));

        $this->actingAs($user)->delete('/transactions/'.$user->transactions()->first()->id);

        $this->assertSame(0, RecurringTransaction::count());
    }

    public function test_deleting_one_repetition_keeps_the_series(): void
    {
        Carbon::setTestNow('2026-01-10 10:00');
        $user = User::factory()->create();
        $this->actingAs($user)->post('/transactions', $this->expense($user, ['is_fixed' => 1]));
        Carbon::setTestNow('2026-02-10 10:00');
        $this->artisan('recurring:run');

        $this->actingAs($user)->delete('/transactions/'.$user->transactions()->latest('transaction_date')->first()->id);

        $this->assertTrue(RecurringTransaction::sole()->is_active);
    }

    public function test_account_can_be_marked_as_accepting_installments(): void
    {
        $this->seed(CurrencySeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/accounts', [
            'name' => 'Master', 'type' => 'card', 'currency_code' => 'ARS',
            'initial_balance' => 0, 'accepts_installments' => true,
        ])->assertSessionHasNoErrors();

        $this->assertTrue($user->accounts()->where('name', 'Master')->sole()->accepts_installments);
    }

    public function test_fixed_expense_created_offline_syncs(): void
    {
        $user = User::factory()->create();
        $op = [
            'op_id' => (string) Str::uuid(), 'entity' => 'transaction', 'action' => 'create',
            'temp_id' => 'tmp:x', 'data' => $this->expense($user, ['is_fixed' => true]),
        ];

        $this->actingAs($user)->post('/sync', ['ops' => json_encode([$op])])
            ->assertJsonPath('results.0.status', 'ok');

        $this->assertSame('fixed', RecurringTransaction::sole()->kind);
    }
}

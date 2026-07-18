<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountsAndTransfersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CurrencySeeder::class);
    }

    public function test_account_balance_is_computed_from_transactions(): void
    {
        $user = User::factory()->create();
        $account = $user->accounts()->first();
        $category = $user->categories()->where('type', 'expense')->first();
        $incomeCat = $user->categories()->where('type', 'income')->first();

        $user->transactions()->create([
            'account_id' => $account->id, 'category_id' => $incomeCat->id, 'type' => 'income',
            'amount' => 1000, 'currency_code' => 'ARS', 'exchange_rate' => 1, 'transaction_date' => now(),
        ]);
        $user->transactions()->create([
            'account_id' => $account->id, 'category_id' => $category->id, 'type' => 'expense',
            'amount' => 300, 'currency_code' => 'ARS', 'exchange_rate' => 1, 'transaction_date' => now(),
        ]);

        $this->assertSame(700.0, $account->fresh()->currentBalance());
    }

    public function test_user_can_create_and_cannot_delete_account_with_transactions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/accounts', [
            'name' => 'Banco', 'type' => 'bank', 'currency_code' => 'USD',
            'initial_balance' => 0, 'color' => '#3b82f6', 'icon' => 'building-bank',
        ])->assertSessionHasNoErrors();

        $bank = $user->accounts()->where('name', 'Banco')->first();
        $this->assertNotNull($bank);
        $this->assertSame('USD', $bank->currency_code);

        // Cuenta con movimiento no se puede eliminar
        $cat = $user->categories()->where('type', 'expense')->first();
        $user->transactions()->create([
            'account_id' => $bank->id, 'category_id' => $cat->id, 'type' => 'expense',
            'amount' => 10, 'currency_code' => 'USD', 'exchange_rate' => 1000, 'transaction_date' => now(),
        ]);

        $this->actingAs($user)->delete(route('accounts.destroy', $bank->id))
            ->assertSessionHasErrors('account');
        $this->assertDatabaseHas('accounts', ['id' => $bank->id]);
    }

    public function test_transfer_moves_balance_between_accounts(): void
    {
        $user = User::factory()->create();
        $from = $user->accounts()->first();
        $to = $user->accounts()->create([
            'name' => 'Banco', 'type' => 'bank', 'currency_code' => 'ARS', 'initial_balance' => 0,
        ]);

        $this->actingAs($user)->post('/transactions', [
            'type' => 'transfer',
            'account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => 500,
            'transfer_amount' => 500,
            'transaction_date' => now()->toDateString(),
        ])->assertRedirect(route('dashboard'));

        $this->assertSame(-500.0, $from->fresh()->currentBalance());
        $this->assertSame(500.0, $to->fresh()->currentBalance());
    }

    public function test_cross_currency_transfer_uses_destination_amount(): void
    {
        $user = User::factory()->create();
        $from = $user->accounts()->first(); // ARS
        $to = $user->accounts()->create([
            'name' => 'USD', 'type' => 'bank', 'currency_code' => 'USD', 'initial_balance' => 0,
        ]);

        $this->actingAs($user)->post('/transactions', [
            'type' => 'transfer',
            'account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => 100000,   // ARS que salen
            'transfer_amount' => 100, // USD que entran
            'transaction_date' => now()->toDateString(),
        ])->assertRedirect(route('dashboard'));

        $this->assertSame(-100000.0, $from->fresh()->currentBalance());
        $this->assertSame(100.0, $to->fresh()->currentBalance());
    }

    public function test_dashboard_totals_convert_foreign_currency_to_base(): void
    {
        $user = User::factory()->create();
        $usd = $user->accounts()->create([
            'name' => 'USD', 'type' => 'bank', 'currency_code' => 'USD', 'initial_balance' => 0,
        ]);
        $ars = $user->accounts()->first();
        $cat = $user->categories()->where('type', 'expense')->first();

        // 100 USD a cotización 1000 => 100.000 ARS
        $user->transactions()->create([
            'account_id' => $usd->id, 'category_id' => $cat->id, 'type' => 'expense',
            'amount' => 100, 'currency_code' => 'USD', 'exchange_rate' => 1000, 'transaction_date' => now(),
        ]);
        // 5.000 ARS
        $user->transactions()->create([
            'account_id' => $ars->id, 'category_id' => $cat->id, 'type' => 'expense',
            'amount' => 5000, 'currency_code' => 'ARS', 'exchange_rate' => 1, 'transaction_date' => now(),
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('totals.expense', fn ($v) => (float) $v === 105000.0)
            );
    }

    public function test_transfers_are_excluded_from_expense_and_income_totals(): void
    {
        $user = User::factory()->create();
        $from = $user->accounts()->first();
        $to = $user->accounts()->create([
            'name' => 'Banco', 'type' => 'bank', 'currency_code' => 'ARS', 'initial_balance' => 0,
        ]);

        $user->transactions()->create([
            'account_id' => $from->id, 'to_account_id' => $to->id, 'type' => 'transfer',
            'amount' => 500, 'transfer_amount' => 500, 'currency_code' => 'ARS', 'transfer_currency' => 'ARS',
            'exchange_rate' => 1, 'transaction_date' => now(),
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('totals.expense', fn ($v) => (float) $v === 0.0)
                ->where('totals.income', fn ($v) => (float) $v === 0.0)
                ->has('transactions', 1)
            );
    }
}

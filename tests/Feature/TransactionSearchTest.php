<?php

namespace Tests\Feature;

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionSearchTest extends TestCase
{
    use RefreshDatabase;

    private function addTransaction(User $user, string $type, string $note, string $date = '2026-07-17', array $extra = []): void
    {
        $account = $user->accounts()->first();
        $category = $user->categories()->where('type', $type === 'transfer' ? 'expense' : $type)->first();

        $user->transactions()->create([
            'account_id' => $account->id,
            'category_id' => $type === 'transfer' ? null : $category->id,
            'type' => $type,
            'amount' => 1000,
            'currency_code' => 'ARS',
            'exchange_rate' => 1,
            'note' => $note,
            'transaction_date' => $date,
            ...$extra,
        ]);
    }

    public function test_finds_income_and_expenses_by_note_across_months(): void
    {
        $user = User::factory()->create();
        $this->addTransaction($user, 'expense', 'Supermercado Coto', '2025-03-02');
        $this->addTransaction($user, 'income', 'Reintegro supermercado', '2026-07-17');
        $this->addTransaction($user, 'expense', 'Nafta');
        $this->addTransaction($user, 'transfer', 'Supermercado a ahorro', '2026-07-17', [
            'to_account_id' => $user->accounts()->create(['name' => 'Ahorro', 'type' => 'bank', 'currency_code' => 'ARS'])->id,
        ]);

        $this->actingAs($user)
            ->get('/transactions/search?q=supermercado')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Search')
                ->where('query', 'supermercado')
                ->has('transactions', 2)
                ->where('transactions.0.note', 'Reintegro supermercado')
                ->where('transactions.1.note', 'Supermercado Coto'));
    }

    public function test_does_not_show_other_users_transactions(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->addTransaction($other, 'expense', 'Supermercado');

        $this->actingAs($user)
            ->get('/transactions/search?q=supermercado')
            ->assertInertia(fn (Assert $page) => $page->has('transactions', 0));
    }

    public function test_like_wildcards_are_searched_literally(): void
    {
        $user = User::factory()->create();
        $this->addTransaction($user, 'expense', 'Descuento 10% off');
        $this->addTransaction($user, 'expense', 'Cualquier cosa');

        $this->actingAs($user)
            ->get('/transactions/search?q=%25')
            ->assertInertia(fn (Assert $page) => $page
                ->has('transactions', 1)
                ->where('transactions.0.note', 'Descuento 10% off'));
    }

    public function test_empty_query_returns_no_results(): void
    {
        $user = User::factory()->create();
        $this->addTransaction($user, 'expense', 'Supermercado');

        $this->actingAs($user)
            ->get('/transactions/search')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('query', '')->has('transactions', 0));
    }
}

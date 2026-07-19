<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TransactionEditTest extends TestCase
{
    use RefreshDatabase;

    private function makeExpense(User $user, array $overrides = []): Transaction
    {
        $account = $user->accounts()->first();
        $cat = $user->categories()->where('type', 'expense')->first();

        return $user->transactions()->create(array_merge([
            'account_id' => $account->id,
            'category_id' => $cat->id,
            'type' => 'expense',
            'amount' => 100,
            'currency_code' => 'ARS',
            'exchange_rate' => 1,
            'transaction_date' => now(),
        ], $overrides));
    }

    public function test_edit_screen_returns_the_transaction_data(): void
    {
        $user = User::factory()->create();
        $txn = $this->makeExpense($user);

        $this->actingAs($user)->get(route('transactions.edit', $txn->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Create')
                ->where('transaction.id', $txn->id)
                ->where('transaction.amount', fn ($v) => (float) $v === 100.0)
                ->where('transaction.account_id', $txn->account_id)
                ->where('transaction.category_id', $txn->category_id)
            );
    }

    public function test_user_can_update_an_expense(): void
    {
        $user = User::factory()->create();
        $txn = $this->makeExpense($user, ['amount' => 100]);
        $otherCat = $user->categories()->where('type', 'expense')->where('id', '!=', $txn->category_id)->first();

        $this->actingAs($user)->post(route('transactions.update', $txn->id), [
            'type' => 'expense',
            'amount' => 250,
            'account_id' => $txn->account_id,
            'category_id' => $otherCat->id,
            'note' => 'editado',
            'transaction_date' => now()->toDateString(),
            'exchange_rate' => '',
            'photo' => '',
        ])->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();

        $txn->refresh();
        $this->assertEquals(250, $txn->amount);
        $this->assertEquals($otherCat->id, $txn->category_id);
        $this->assertSame('editado', $txn->note);
    }

    public function test_update_recalculates_account_balance(): void
    {
        $user = User::factory()->create();
        $account = $user->accounts()->first();
        $txn = $this->makeExpense($user, ['amount' => 100]);

        $this->assertEquals(-100, $account->currentBalance());

        $this->actingAs($user)->post(route('transactions.update', $txn->id), [
            'type' => 'expense',
            'amount' => 250,
            'account_id' => $account->id,
            'category_id' => $txn->category_id,
            'transaction_date' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertEquals(-250, $account->fresh()->currentBalance());
    }

    public function test_user_can_update_a_transfer(): void
    {
        $user = User::factory()->create();
        $from = $user->accounts()->first();
        $to = $user->accounts()->create([
            'name' => 'Banco', 'type' => 'bank', 'currency_code' => 'ARS',
            'initial_balance' => 0, 'color' => '#3b82f6', 'icon' => 'building-bank', 'sort_order' => 2,
        ]);

        $txn = $user->transactions()->create([
            'account_id' => $from->id, 'to_account_id' => $to->id, 'type' => 'transfer',
            'amount' => 500, 'currency_code' => 'ARS', 'exchange_rate' => 1,
            'transfer_amount' => 500, 'transfer_currency' => 'ARS', 'transaction_date' => now(),
        ]);

        $this->actingAs($user)->post(route('transactions.update', $txn->id), [
            'type' => 'transfer',
            'account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => 700,
            'transfer_amount' => 700,
            'transaction_date' => now()->toDateString(),
        ])->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();

        $txn->refresh();
        $this->assertEquals(700, $txn->amount);
        $this->assertEquals(700, $txn->transfer_amount);
        // saldos: origen -700, destino +700
        $this->assertEquals(-700, $from->fresh()->currentBalance());
        $this->assertEquals(700, $to->fresh()->currentBalance());
    }

    public function test_user_can_delete_a_transaction(): void
    {
        $user = User::factory()->create();
        $txn = $this->makeExpense($user);

        $this->actingAs($user)->delete(route('transactions.destroy', $txn->id))
            ->assertRedirect();

        $this->assertModelMissing($txn);
    }

    public function test_cannot_touch_another_users_transaction(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $txn = $this->makeExpense($owner);

        $this->actingAs($other)->get(route('transactions.edit', $txn->id))->assertForbidden();
        $this->actingAs($other)->post(route('transactions.update', $txn->id), [
            'type' => 'expense', 'amount' => 5, 'account_id' => $txn->account_id,
            'category_id' => $txn->category_id, 'transaction_date' => now()->toDateString(),
        ])->assertForbidden();
        $this->actingAs($other)->delete(route('transactions.destroy', $txn->id))->assertForbidden();

        $this->assertModelExists($txn);
    }
}

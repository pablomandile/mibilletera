<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BudgetsAndReportsTest extends TestCase
{
    use RefreshDatabase;

    private function expenseCategory(User $user)
    {
        return $user->categories()->where('type', 'expense')->first();
    }

    public function test_user_can_set_and_update_a_category_budget(): void
    {
        $user = User::factory()->create();
        $cat = $this->expenseCategory($user);

        $this->actingAs($user)->post('/budgets', ['category_id' => $cat->id, 'amount' => 50000])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('budgets', ['user_id' => $user->id, 'category_id' => $cat->id, 'amount' => 50000]);

        // updateOrCreate: no duplica, actualiza
        $this->actingAs($user)->post('/budgets', ['category_id' => $cat->id, 'amount' => 80000]);
        $this->assertSame(1, $user->budgets()->where('category_id', $cat->id)->count());
        $this->assertEquals(80000, $user->budgets()->where('category_id', $cat->id)->first()->amount);
    }

    public function test_budget_index_computes_spent_for_the_month(): void
    {
        $user = User::factory()->create();
        $cat = $this->expenseCategory($user);
        $account = $user->accounts()->first();

        $user->transactions()->create([
            'account_id' => $account->id, 'category_id' => $cat->id, 'type' => 'expense',
            'amount' => 12000, 'currency_code' => 'ARS', 'exchange_rate' => 1, 'transaction_date' => now(),
        ]);
        $user->budgets()->create(['category_id' => $cat->id, 'amount' => 50000, 'currency_code' => 'ARS']);

        $this->actingAs($user)->get('/budgets')
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($cat) {
                $page->component('Settings/Budgets');
                $row = (array) collect($page->toArray()['props']['categories'])->firstWhere('id', $cat->id);
                $this->assertSame(12000.0, (float) $row['spent']);
                $this->assertSame(50000.0, (float) $row['budget']);
            });
    }

    public function test_user_can_set_global_budget_and_delete_it(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/budgets', ['category_id' => null, 'amount' => 300000])
            ->assertSessionHasNoErrors();
        $budget = $user->budgets()->whereNull('category_id')->first();
        $this->assertNotNull($budget);

        $this->actingAs($user)->delete(route('budgets.destroy', $budget->id))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('budgets', ['id' => $budget->id]);
    }

    public function test_reports_render_with_category_breakdown(): void
    {
        $user = User::factory()->create();
        $account = $user->accounts()->first();
        $cat = $this->expenseCategory($user);

        $user->transactions()->create([
            'account_id' => $account->id, 'category_id' => $cat->id, 'type' => 'expense',
            'amount' => 9000, 'currency_code' => 'ARS', 'exchange_rate' => 1, 'transaction_date' => now(),
        ]);

        $this->actingAs($user)->get('/reports')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Index')
                ->where('totals.expense', fn ($v) => (float) $v === 9000.0)
                ->has('expenseByCategory', 1)
            );
    }
}

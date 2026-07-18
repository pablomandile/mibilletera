<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringAndRemindersTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    public function test_command_generates_due_transactions_with_catch_up(): void
    {
        $user = $this->makeUser();
        $account = $user->accounts()->first();
        $cat = $user->categories()->where('type', 'expense')->first();

        $rec = $user->recurringTransactions()->create([
            'account_id' => $account->id,
            'category_id' => $cat->id,
            'type' => 'expense',
            'amount' => 1000,
            'currency_code' => 'ARS',
            'exchange_rate' => 1,
            'frequency' => 'weekly',
            'interval' => 1,
            'next_run_date' => now()->subWeeks(2)->toDateString(),
            'is_active' => true,
        ]);

        $this->artisan('recurring:run')->assertSuccessful();

        // Debe haber generado 3 transacciones (semana -2, -1 y hoy).
        $this->assertSame(3, $user->transactions()->where('type', 'expense')->count());
        $this->assertTrue($rec->fresh()->next_run_date->isFuture());
    }

    public function test_command_respects_end_date_and_deactivates(): void
    {
        $user = $this->makeUser();
        $account = $user->accounts()->first();
        $cat = $user->categories()->where('type', 'expense')->first();

        $rec = $user->recurringTransactions()->create([
            'account_id' => $account->id,
            'category_id' => $cat->id,
            'type' => 'expense',
            'amount' => 500,
            'currency_code' => 'ARS',
            'exchange_rate' => 1,
            'frequency' => 'weekly',
            'interval' => 1,
            'next_run_date' => now()->subWeeks(2)->toDateString(),
            'end_date' => now()->subWeek()->toDateString(),
            'is_active' => true,
        ]);

        $this->artisan('recurring:run')->assertSuccessful();

        // Solo semana -2 y -1 (hoy queda fuera del end_date).
        $this->assertSame(2, $user->transactions()->count());
        $this->assertFalse($rec->fresh()->is_active);
    }

    public function test_user_can_create_a_recurring_transaction(): void
    {
        $user = $this->makeUser();
        $account = $user->accounts()->first();
        $cat = $user->categories()->where('type', 'expense')->first();

        $this->actingAs($user)->post('/recurring', [
            'type' => 'expense',
            'account_id' => $account->id,
            'category_id' => $cat->id,
            'amount' => 30000,
            'frequency' => 'monthly',
            'interval' => 1,
            'next_run_date' => now()->toDateString(),
            'note' => 'Alquiler',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('recurring_transactions', [
            'user_id' => $user->id, 'amount' => 30000, 'frequency' => 'monthly', 'note' => 'Alquiler',
        ]);
    }

    public function test_reminder_crud(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('/reminders', [
            'title' => 'Cargar gastos', 'time' => '20:30', 'days' => [1, 3, 5], 'enabled' => true,
        ])->assertSessionHasNoErrors();

        $reminder = $user->reminders()->first();
        $this->assertNotNull($reminder);
        $this->assertSame([1, 3, 5], $reminder->days);

        $this->actingAs($user)->patch(route('reminders.update', $reminder->id), [
            'title' => 'Cargar gastos', 'time' => '21:00', 'days' => [], 'enabled' => false,
        ])->assertSessionHasNoErrors();
        $this->assertFalse($reminder->fresh()->enabled);

        $this->actingAs($user)->delete(route('reminders.destroy', $reminder->id))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('reminders', ['id' => $reminder->id]);
    }
}

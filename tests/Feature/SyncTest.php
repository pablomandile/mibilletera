<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * POST /sync: los cambios que la app encoló mientras no tenía conexión.
 */
class SyncTest extends TestCase
{
    use RefreshDatabase;

    private function op(string $entity, string $action, array $data = [], array $extra = []): array
    {
        return ['op_id' => (string) Str::uuid(), 'entity' => $entity, 'action' => $action, 'data' => $data] + $extra;
    }

    private function sync(User $user, array $ops, array $files = [])
    {
        return $this->actingAs($user)->post('/sync', ['ops' => json_encode($ops), 'files' => $files]);
    }

    private function expense(User $user, array $overrides = []): array
    {
        return $overrides + [
            'type' => 'expense',
            'amount' => 1500,
            'account_id' => $user->accounts()->first()->id,
            'category_id' => $user->categories()->where('type', 'expense')->first()->id,
            'note' => 'Sin conexión',
            'transaction_date' => '2026-10-13',
        ];
    }

    public function test_creates_a_transaction(): void
    {
        $user = User::factory()->create();

        $this->sync($user, [$this->op('transaction', 'create', $this->expense($user), ['temp_id' => 'tmp:a'])])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'ok')
            ->assertJsonPath('results.0.temp_id', 'tmp:a');

        $this->assertDatabaseHas('transactions', ['user_id' => $user->id, 'note' => 'Sin conexión', 'amount' => 1500]);
    }

    public function test_resending_the_same_operation_does_not_duplicate_it(): void
    {
        $user = User::factory()->create();
        $op = $this->op('transaction', 'create', $this->expense($user), ['temp_id' => 'tmp:a']);

        $first = $this->sync($user, [$op])->json('results.0');
        $second = $this->sync($user, [$op])->json('results.0');

        $this->assertSame('ok', $second['status']);
        $this->assertTrue($second['duplicate']);
        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, Transaction::where('user_id', $user->id)->count());
    }

    public function test_temporary_ids_resolve_within_the_same_batch(): void
    {
        $this->seed(CurrencySeeder::class);
        $user = User::factory()->create();

        $this->sync($user, [
            $this->op('category', 'create', ['type' => 'expense', 'name' => 'Nueva', 'icon_type' => 'preset', 'icon_value' => 'tag'], ['temp_id' => 'tmp:cat']),
            $this->op('account', 'create', ['name' => 'Billetera', 'type' => 'cash', 'currency_code' => 'ARS', 'initial_balance' => 0], ['temp_id' => 'tmp:acc']),
            $this->op('transaction', 'create', $this->expense($user, ['category_id' => 'tmp:cat', 'account_id' => 'tmp:acc'])),
        ])->assertJsonPath('results.2.status', 'ok');

        $transaction = Transaction::where('user_id', $user->id)->latest('id')->first();
        $this->assertSame('Nueva', $transaction->category->name);
        $this->assertSame('Billetera', $transaction->account->name);
    }

    public function test_temporary_ids_resolve_across_batches(): void
    {
        $user = User::factory()->create();

        $catId = $this->sync($user, [
            $this->op('category', 'create', ['type' => 'expense', 'name' => 'Nueva', 'icon_type' => 'preset', 'icon_value' => 'tag'], ['temp_id' => 'tmp:cat']),
        ])->json('results.0.id');

        $this->sync($user, [
            $this->op('transaction', 'create', $this->expense($user, ['category_id' => 'tmp:cat']), ['temp_id' => 'tmp:tx']),
            // Editar y borrar algo creado sin conexión también usa su id temporal.
            $this->op('category', 'update', ['type' => 'expense', 'name' => 'Renombrada', 'icon_type' => 'preset', 'icon_value' => 'tag'], ['id' => 'tmp:cat']),
            $this->op('transaction', 'delete', [], ['id' => 'tmp:tx']),
        ])->assertJsonPath('results.1.status', 'ok')->assertJsonPath('results.2.status', 'ok');

        $this->assertSame('Renombrada', Category::find($catId)->name);
        $this->assertSame(0, Transaction::where('user_id', $user->id)->count());
    }

    public function test_a_validation_error_does_not_stop_independent_operations(): void
    {
        $user = User::factory()->create();

        $results = $this->sync($user, [
            $this->op('category', 'create', ['type' => 'expense', 'name' => ''], ['temp_id' => 'tmp:bad']),
            $this->op('transaction', 'create', $this->expense($user, ['category_id' => 'tmp:bad'])),
            $this->op('transaction', 'create', $this->expense($user, ['note' => 'Independiente'])),
        ])->json('results');

        $this->assertSame('error', $results[0]['status']);
        $this->assertArrayHasKey('name', $results[0]['errors']);
        // Depende de la categoría que falló: queda esperando, no se pierde.
        $this->assertSame('blocked', $results[1]['status']);
        $this->assertSame('ok', $results[2]['status']);
        $this->assertDatabaseHas('transactions', ['user_id' => $user->id, 'note' => 'Independiente']);
    }

    public function test_cannot_touch_another_users_data(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $transaction = $owner->transactions()->create($this->expense($owner) + ['currency_code' => 'ARS', 'exchange_rate' => 1]);

        $results = $this->sync($intruder, [
            $this->op('transaction', 'update', $this->expense($intruder), ['id' => $transaction->id]),
            $this->op('transaction', 'delete', [], ['id' => $transaction->id]),
            // Usar una cuenta ajena en un alta propia tampoco.
            $this->op('transaction', 'create', $this->expense($intruder, ['account_id' => $owner->accounts()->first()->id])),
        ])->json('results');

        $this->assertSame('error', $results[0]['status']);
        $this->assertSame('error', $results[2]['status']);
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'note' => 'Sin conexión']);
    }

    public function test_deleting_something_already_deleted_is_fine(): void
    {
        $user = User::factory()->create();

        $this->sync($user, [$this->op('transaction', 'delete', [], ['id' => 999999])])
            ->assertJsonPath('results.0.status', 'ok');
    }

    public function test_business_rules_apply_offline_too(): void
    {
        $user = User::factory()->create();
        $account = $user->accounts()->first();
        $user->transactions()->create($this->expense($user) + ['currency_code' => 'ARS', 'exchange_rate' => 1]);

        $this->sync($user, [$this->op('account', 'delete', [], ['id' => $account->id])])
            ->assertJsonPath('results.0.status', 'error')
            ->assertJsonPath('results.0.errors.account.0', 'No se puede eliminar una cuenta con movimientos. Archivala en su lugar.');
    }

    public function test_attaches_the_photo_of_an_offline_expense(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $op = $this->op('transaction', 'create', $this->expense($user));

        $this->sync($user, [$op], [$op['op_id'] => UploadedFile::fake()->image('ticket.jpg')])
            ->assertJsonPath('results.0.status', 'ok');

        $transaction = Transaction::where('user_id', $user->id)->first();
        $this->assertNotNull($transaction->photo_path);
        Storage::disk('public')->assertExists($transaction->photo_path);
    }

    public function test_other_entities_and_preferences(): void
    {
        $this->seed(CurrencySeeder::class);
        $user = User::factory()->create();
        $category = $user->categories()->where('type', 'expense')->first();

        $results = $this->sync($user, [
            $this->op('budget', 'create', ['category_id' => $category->id, 'amount' => 50000]),
            $this->op('reminder', 'create', ['title' => 'Cargar gastos', 'time' => '21:00', 'days' => [1, 3], 'enabled' => true]),
            $this->op('recurring', 'create', [
                'type' => 'expense', 'account_id' => $user->accounts()->first()->id, 'category_id' => $category->id,
                'amount' => 9000, 'frequency' => 'monthly', 'interval' => 1, 'next_run_date' => '2026-11-01',
            ]),
            $this->op('preferences', 'update', ['default_currency' => 'USD']),
            $this->op('budget', 'update', ['amount' => 1]), // no existe: crear reemplaza
        ])->json('results');

        $this->assertSame(['ok', 'ok', 'ok', 'ok', 'error'], array_column($results, 'status'));
        $this->assertDatabaseHas('budgets', ['user_id' => $user->id, 'category_id' => $category->id, 'amount' => 50000]);
        $this->assertDatabaseHas('reminders', ['user_id' => $user->id, 'title' => 'Cargar gastos']);
        $this->assertDatabaseHas('recurring_transactions', ['user_id' => $user->id, 'amount' => 9000]);
        $this->assertSame('USD', $user->fresh()->default_currency);
    }

    public function test_requires_login(): void
    {
        $this->post('/sync', ['ops' => '[]'])->assertRedirect('/login');
    }
}

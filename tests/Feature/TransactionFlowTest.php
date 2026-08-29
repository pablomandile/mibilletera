<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TransactionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_gets_default_account_and_categories(): void
    {
        $user = User::factory()->create();

        $this->assertSame(1, $user->accounts()->count());
        $this->assertSame('ARS', $user->accounts()->first()->currency_code);
        $this->assertGreaterThan(20, $user->categories()->where('type', 'expense')->count());
        $this->assertGreaterThan(0, $user->categories()->where('type', 'income')->count());
    }

    public function test_user_can_store_an_expense(): void
    {
        $user = User::factory()->create();
        $account = $user->accounts()->first();
        $category = $user->categories()->where('type', 'expense')->first();

        $response = $this->actingAs($user)->post('/transactions', [
            'type' => 'expense',
            'amount' => 20000,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'note' => 'Supermercado',
            'transaction_date' => '2026-07-17',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'amount' => 20000,
            'currency_code' => 'ARS',
            'note' => 'Supermercado',
        ]);
    }

    public function test_store_requires_category_and_positive_amount(): void
    {
        $user = User::factory()->create();
        $account = $user->accounts()->first();

        $this->actingAs($user)
            ->post('/transactions', [
                'type' => 'expense',
                'amount' => 0,
                'account_id' => $account->id,
                'transaction_date' => '2026-07-17',
            ])
            ->assertSessionHasErrors(['amount', 'category_id']);
    }

    public function test_user_can_create_custom_category_with_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/categories', [
            'type' => 'expense',
            'name' => 'Coto',
            'icon_type' => 'image',
            'color' => '#ef4444',
            'image' => UploadedFile::fake()->image('logo.png', 120, 120),
        ]);

        $response->assertSessionHasNoErrors();

        $category = Category::where('user_id', $user->id)->where('name', 'Coto')->first();
        $this->assertNotNull($category);
        $this->assertSame('image', $category->icon_type);
        Storage::disk('public')->assertExists($category->icon_value);
    }

    public function test_dashboard_and_charts_render_with_data(): void
    {
        $user = User::factory()->create();
        $account = $user->accounts()->first();
        $category = $user->categories()->where('type', 'expense')->first();

        $user->transactions()->create([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'amount' => 15000,
            'currency_code' => 'ARS',
            'exchange_rate' => 1,
            'transaction_date' => now(),
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('transactions', 1)
                ->where('totals.expense', fn ($v) => (float) $v === 15000.0)
            );

        $this->actingAs($user)->get('/charts?range=month')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Charts/Index')
                ->where('range', 'month')
                ->has('byCategory', 1)
            );
    }

    public function test_dashboard_filters_the_list_by_type_without_touching_totals(): void
    {
        $user = User::factory()->create();
        $account = $user->accounts()->first();
        $expenseCat = $user->categories()->where('type', 'expense')->first();
        $incomeCat = $user->categories()->where('type', 'income')->first();

        $user->transactions()->create([
            'account_id' => $account->id, 'category_id' => $expenseCat->id, 'type' => 'expense',
            'amount' => 15000, 'currency_code' => 'ARS', 'exchange_rate' => 1, 'transaction_date' => now(),
        ]);
        $user->transactions()->create([
            'account_id' => $account->id, 'category_id' => $incomeCat->id, 'type' => 'income',
            'amount' => 90000, 'currency_code' => 'ARS', 'exchange_rate' => 1, 'transaction_date' => now(),
        ]);

        // Sin filtro: los dos movimientos.
        $this->actingAs($user)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->has('transactions', 2)->where('filter', null));

        // Filtrado: solo el gasto, pero los totales siguen siendo los del mes completo.
        $this->actingAs($user)->get('/dashboard?type=expense')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filter', 'expense')
                ->has('transactions', 1)
                ->where('transactions.0.type', 'expense')
                ->where('totals.income', fn ($v) => (float) $v === 90000.0)
                ->where('totals.expense', fn ($v) => (float) $v === 15000.0)
            );

        $this->actingAs($user)->get('/dashboard?type=income')
            ->assertInertia(fn (Assert $page) => $page
                ->has('transactions', 1)
                ->where('transactions.0.type', 'income')
            );

        // Un tipo inválido se ignora en vez de vaciar el listado.
        $this->actingAs($user)->get('/dashboard?type=cualquiera')
            ->assertInertia(fn (Assert $page) => $page->where('filter', null)->has('transactions', 2));
    }

    public function test_dashboard_sends_day_totals_converted_to_base_currency(): void
    {
        $user = User::factory()->create();
        $account = $user->accounts()->first();
        $usd = $user->accounts()->create([
            'name' => 'USD', 'type' => 'bank', 'currency_code' => 'USD', 'initial_balance' => 0,
        ]);
        $expenseCat = $user->categories()->where('type', 'expense')->first();
        $incomeCat = $user->categories()->where('type', 'income')->first();

        $dayA = now()->startOfMonth();
        $dayB = now()->startOfMonth()->addDay();

        // Día A: 15.000 ARS + 100 USD a 1000 => 115.000 en moneda base.
        $user->transactions()->create([
            'account_id' => $account->id, 'category_id' => $expenseCat->id, 'type' => 'expense',
            'amount' => 15000, 'currency_code' => 'ARS', 'exchange_rate' => 1, 'transaction_date' => $dayA,
        ]);
        $user->transactions()->create([
            'account_id' => $usd->id, 'category_id' => $expenseCat->id, 'type' => 'expense',
            'amount' => 100, 'currency_code' => 'USD', 'exchange_rate' => 1000, 'transaction_date' => $dayA,
        ]);
        // La transferencia del día A no debe sumar a ningún lado.
        $user->transactions()->create([
            'account_id' => $account->id, 'to_account_id' => $usd->id, 'type' => 'transfer',
            'amount' => 7000, 'transfer_amount' => 7, 'currency_code' => 'ARS', 'transfer_currency' => 'USD',
            'exchange_rate' => 1, 'transaction_date' => $dayA,
        ]);
        // Día B: solo un ingreso.
        $user->transactions()->create([
            'account_id' => $account->id, 'category_id' => $incomeCat->id, 'type' => 'income',
            'amount' => 90000, 'currency_code' => 'ARS', 'exchange_rate' => 1, 'transaction_date' => $dayB,
        ]);

        $keyA = $dayA->format('Y-m-d');
        $keyB = $dayB->format('Y-m-d');

        $this->actingAs($user)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('dayTotals', 2)
                ->where("dayTotals.{$keyA}.expense", fn ($v) => (float) $v === 115000.0)
                ->where("dayTotals.{$keyA}.income", fn ($v) => (float) $v === 0.0)
                ->where("dayTotals.{$keyB}.expense", fn ($v) => (float) $v === 0.0)
                ->where("dayTotals.{$keyB}.income", fn ($v) => (float) $v === 90000.0)
            );
    }

    public function test_day_totals_ignore_the_list_filter(): void
    {
        $user = User::factory()->create();
        $account = $user->accounts()->first();
        $expenseCat = $user->categories()->where('type', 'expense')->first();
        $incomeCat = $user->categories()->where('type', 'income')->first();

        $day = now()->startOfMonth();

        $user->transactions()->create([
            'account_id' => $account->id, 'category_id' => $expenseCat->id, 'type' => 'expense',
            'amount' => 15000, 'currency_code' => 'ARS', 'exchange_rate' => 1, 'transaction_date' => $day,
        ]);
        $user->transactions()->create([
            'account_id' => $account->id, 'category_id' => $incomeCat->id, 'type' => 'income',
            'amount' => 90000, 'currency_code' => 'ARS', 'exchange_rate' => 1, 'transaction_date' => $day,
        ]);

        $key = $day->format('Y-m-d');

        // El filtro achica el listado, pero el separador sigue mostrando el día completo.
        $this->actingAs($user)->get('/dashboard?type=expense')
            ->assertInertia(fn (Assert $page) => $page
                ->has('transactions', 1)
                ->where("dayTotals.{$key}.expense", fn ($v) => (float) $v === 15000.0)
                ->where("dayTotals.{$key}.income", fn ($v) => (float) $v === 90000.0)
            );
    }
}

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
}

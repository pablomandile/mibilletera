<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_subcategory(): void
    {
        $user = User::factory()->create();
        $parent = $user->categories()->where('type', 'expense')->first();

        $this->actingAs($user)->post('/categories', [
            'type' => 'expense',
            'name' => 'Delivery',
            'icon_type' => 'preset',
            'icon_value' => 'pizza',
            'color' => '#f97316',
            'parent_id' => $parent->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => 'Delivery',
            'parent_id' => $parent->id,
        ]);
    }

    public function test_categories_index_returns_children(): void
    {
        $user = User::factory()->create();
        $parent = $user->categories()->where('type', 'expense')->first();
        $user->categories()->create([
            'parent_id' => $parent->id, 'type' => 'expense', 'name' => 'Sub',
            'icon_type' => 'preset', 'icon_value' => 'category', 'color' => '#fff',
        ]);

        $this->actingAs($user)->get('/categories')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Categories')
                ->where('categories', fn ($cats) => collect($cats)->contains(fn ($c) => ! empty($c['children'])))
            );
    }

    public function test_user_can_change_default_currency(): void
    {
        $this->seed(CurrencySeeder::class);
        $user = User::factory()->create(['default_currency' => 'ARS']);

        $this->actingAs($user)->patch('/preferences', ['default_currency' => 'USD'])
            ->assertSessionHasNoErrors();

        $this->assertSame('USD', $user->fresh()->default_currency);
    }

    public function test_invalid_currency_is_rejected(): void
    {
        $this->seed(CurrencySeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/preferences', ['default_currency' => 'XXX'])
            ->assertSessionHasErrors('default_currency');
    }
}

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

    public function test_create_preset_category_with_empty_image_field(): void
    {
        // Regresión: el cliente Inertia envía `image` (y `parent_id`) como cadena
        // vacía cuando el ícono es un preset. Sin `nullable` en la regla `image`,
        // Laravel fallaba con "must be an image" y la categoría no se creaba.
        $user = User::factory()->create();

        $this->actingAs($user)->post('/categories', [
            'type' => 'expense',
            'name' => 'Cafetería',
            'icon_type' => 'preset',
            'icon_value' => 'coffee',
            'color' => '#f59e0b',
            'parent_id' => '',
            'image' => '',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => 'Cafetería',
            'icon_type' => 'preset',
            'icon_value' => 'coffee',
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

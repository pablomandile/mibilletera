<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Supercategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupercategoriesTest extends TestCase
{
    use RefreshDatabase;

    private function category(User $user, string $name): Category
    {
        return $user->categories()->create([
            'type' => 'expense', 'name' => $name, 'icon_type' => 'preset', 'icon_value' => 'tag', 'color' => '#22c55e',
        ]);
    }

    private function expense(User $user, Category $category, float $amount): void
    {
        $user->transactions()->create([
            'account_id' => $user->accounts()->first()->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'amount' => $amount,
            'currency_code' => 'ARS',
            'exchange_rate' => 1,
            'transaction_date' => now()->toDateString(),
        ]);
    }

    public function test_creates_a_supercategory_grouping_categories(): void
    {
        $user = User::factory()->create();
        $super = $this->category($user, 'Supermercado');
        $fruta = $this->category($user, 'Fruta');

        $this->actingAs($user)
            ->post('/supercategories', ['name' => 'Comida', 'color' => '#ef4444', 'category_ids' => [$super->id, $fruta->id]])
            ->assertSessionHasNoErrors();

        $comida = Supercategory::where('user_id', $user->id)->sole();
        $this->assertSame('Comida', $comida->name);
        $this->assertEqualsCanonicalizing([$super->id, $fruta->id], $comida->categories()->pluck('id')->all());

        $this->actingAs($user)
            ->get('/supercategories')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Supercategories')
                ->has('supercategories', 1)
                ->has('supercategories.0.category_ids', 2));
    }

    public function test_a_category_moves_when_assigned_to_another_supercategory(): void
    {
        $user = User::factory()->create();
        $bebidas = $this->category($user, 'Bebidas');
        $verdura = $this->category($user, 'Verdura');

        $comida = $user->supercategories()->create(['name' => 'Comida']);
        $salidas = $user->supercategories()->create(['name' => 'Salidas']);
        $bebidas->update(['supercategory_id' => $comida->id]);
        $verdura->update(['supercategory_id' => $comida->id]);

        $this->actingAs($user)
            ->patch("/supercategories/{$salidas->id}", ['name' => 'Salidas', 'category_ids' => [$bebidas->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame($salidas->id, $bebidas->fresh()->supercategory_id);
        $this->assertSame($comida->id, $verdura->fresh()->supercategory_id);

        // Sacarla de la lista la deja suelta.
        $this->actingAs($user)->patch("/supercategories/{$comida->id}", ['name' => 'Comida', 'category_ids' => []]);
        $this->assertNull($verdura->fresh()->supercategory_id);
    }

    public function test_deleting_leaves_its_categories_ungrouped(): void
    {
        $user = User::factory()->create();
        $super = $this->category($user, 'Supermercado');
        $comida = $user->supercategories()->create(['name' => 'Comida']);
        $super->update(['supercategory_id' => $comida->id]);

        $this->actingAs($user)->delete("/supercategories/{$comida->id}")->assertSessionHasNoErrors();

        $this->assertModelMissing($comida);
        $this->assertNull($super->fresh()->supercategory_id);
    }

    public function test_only_own_expense_categories_can_be_grouped(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreign = $this->category($other, 'Ajena');
        $income = $user->categories()->where('type', 'income')->first();

        $this->actingAs($user)
            ->post('/supercategories', ['name' => 'Mezcla', 'category_ids' => [$foreign->id, $income->id]])
            ->assertSessionHasErrors(['category_ids.0', 'category_ids.1']);

        $this->assertSame(0, Supercategory::count());
    }

    public function test_cannot_touch_another_users_supercategory(): void
    {
        $user = User::factory()->create();
        $theirs = User::factory()->create()->supercategories()->create(['name' => 'Ajena']);

        $this->actingAs($user)->patch("/supercategories/{$theirs->id}", ['name' => 'Mía'])->assertForbidden();
        $this->actingAs($user)->delete("/supercategories/{$theirs->id}")->assertForbidden();
    }

    public function test_chart_sums_expenses_by_supercategory(): void
    {
        Carbon::setTestNow('2026-10-21 12:00:00');
        $user = User::factory()->create();
        $super = $this->category($user, 'Supermercado');
        $verdura = $this->category($user, 'Verdura');
        $nafta = $this->category($user, 'Nafta');

        $comida = $user->supercategories()->create(['name' => 'Comida', 'color' => '#ef4444']);
        $super->update(['supercategory_id' => $comida->id]);
        $verdura->update(['supercategory_id' => $comida->id]);

        $this->expense($user, $super, 1000);
        $this->expense($user, $verdura, 500);
        $this->expense($user, $nafta, 300);

        $this->actingAs($user)
            ->get('/charts?range=month')
            ->assertInertia(fn (Assert $page) => $page
                ->where('hasSupercategories', true)
                ->has('bySupercategory', 2)
                ->where('bySupercategory.0.name', 'Comida')
                ->where('bySupercategory.0.total', 1500)
                ->where('bySupercategory.1.name', 'Sin supercategoría')
                ->where('bySupercategory.1.total', 300)
                // El de categorías sigue igual.
                ->has('byCategory', 3));

        Carbon::setTestNow();
    }

    public function test_chart_without_supercategories_sends_nothing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/charts')
            ->assertInertia(fn (Assert $page) => $page
                ->where('hasSupercategories', false)
                ->has('bySupercategory', 0));
    }

    public function test_offline_sync_resolves_a_new_category_inside_the_list(): void
    {
        $user = User::factory()->create();
        $super = $this->category($user, 'Supermercado');
        $op = fn (string $entity, array $data, string $tempId) => [
            'op_id' => (string) Str::uuid(), 'entity' => $entity, 'action' => 'create', 'data' => $data, 'temp_id' => $tempId,
        ];

        $this->actingAs($user)->post('/sync', ['ops' => json_encode([
            $op('category', ['type' => 'expense', 'name' => 'Verdulería', 'icon_type' => 'preset', 'icon_value' => 'tag'], 'tmp:cat'),
            $op('supercategory', ['name' => 'Comida', 'category_ids' => [$super->id, 'tmp:cat']], 'tmp:super'),
        ])])
            ->assertOk()
            ->assertJsonPath('results.1.status', 'ok');

        $comida = Supercategory::where('user_id', $user->id)->sole();
        $this->assertEqualsCanonicalizing(['Supermercado', 'Verdulería'], $comida->categories()->pluck('name')->all());
    }
}

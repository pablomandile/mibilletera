<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Sábado 12:00 (dayOfWeek = 6) para tiempos deterministas.
        $this->travelTo(Carbon::parse('2026-07-18 12:00:00'));
    }

    private function reminder(User $user, array $attrs = []): void
    {
        $user->reminders()->create(array_merge([
            'title' => 'Cargar gastos', 'time' => '09:00', 'days' => [], 'enabled' => true,
        ], $attrs));
    }

    public function test_dispatch_creates_alert_for_due_reminder(): void
    {
        $user = User::factory()->create();
        $this->reminder($user);

        $this->artisan('reminders:dispatch')->assertSuccessful();

        $this->assertSame(1, $user->alerts()->count());
        $this->assertNull($user->alerts()->first()->read_at);
    }

    public function test_dispatch_is_idempotent_per_day(): void
    {
        $user = User::factory()->create();
        $this->reminder($user);

        $this->artisan('reminders:dispatch');
        $this->artisan('reminders:dispatch');

        $this->assertSame(1, $user->alerts()->count());
    }

    public function test_dispatch_skips_future_time_and_wrong_days(): void
    {
        $user = User::factory()->create();
        $this->reminder($user, ['time' => '15:00']);            // futura (ahora 12:00)
        $this->reminder($user, ['time' => '08:00', 'days' => [1, 2, 3]]); // hoy es sábado (6)

        $this->artisan('reminders:dispatch');

        $this->assertSame(0, $user->alerts()->count());
    }

    public function test_mark_read_clears_unread(): void
    {
        $user = User::factory()->create();
        Alert::create(['user_id' => $user->id, 'title' => 'A']);
        Alert::create(['user_id' => $user->id, 'title' => 'B']);

        $this->actingAs($user)->post(route('notifications.read'))->assertRedirect();

        $this->assertSame(0, $user->alerts()->whereNull('read_at')->count());
    }

    public function test_user_can_delete_an_alert(): void
    {
        $user = User::factory()->create();
        $alert = Alert::create(['user_id' => $user->id, 'title' => 'A']);

        $this->actingAs($user)->delete(route('notifications.destroy', $alert->id))->assertRedirect();
        $this->assertDatabaseMissing('alerts', ['id' => $alert->id]);
    }

    public function test_notifications_are_shared_to_the_frontend(): void
    {
        $user = User::factory()->create();
        Alert::create(['user_id' => $user->id, 'title' => 'Nueva']);

        $this->actingAs($user)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('notifications.unread', 1)
                ->has('notifications.items', 1)
            );
    }
}

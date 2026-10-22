<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_blocks_registration_google_and_password_recovery(): void
    {
        config(['demo.enabled' => true]);

        $this->get('/register')->assertRedirect('/login');
        $this->post('/register', ['name' => 'X', 'email' => 'x@x.com', 'password' => 'secreto123', 'password_confirmation' => 'secreto123'])
            ->assertRedirect('/login');
        $this->get('/auth/google/redirect')->assertRedirect('/login');
        $this->get('/forgot-password')->assertRedirect('/login');
        $this->post('/forgot-password', ['email' => 'demo@pablomandile.com.ar'])->assertSessionHas('error');

        $this->assertSame(0, User::count());
    }

    public function test_demo_user_cannot_change_or_delete_the_account(): void
    {
        config(['demo.enabled' => true]);
        $user = User::factory()->create(['password' => 'demo1234']);

        $this->actingAs($user)->from('/profile')
            ->put('/password', ['current_password' => 'demo1234', 'password' => 'otraclave1', 'password_confirmation' => 'otraclave1'])
            ->assertRedirect('/profile')
            ->assertSessionHas('error');
        $this->actingAs($user)->from('/profile')->patch('/profile', ['name' => 'Otro', 'email' => 'otro@x.com'])->assertSessionHas('error');
        $this->actingAs($user)->from('/profile')->delete('/profile', ['password' => 'demo1234'])->assertSessionHas('error');

        $user->refresh();
        $this->assertTrue(Hash::check('demo1234', $user->password));
        $this->assertNotSame('otro@x.com', $user->email);
        $this->assertModelExists($user);
    }

    public function test_login_shares_the_demo_credentials_only_in_demo(): void
    {
        config(['demo.enabled' => true]);
        $this->get('/login')->assertInertia(fn (Assert $page) => $page
            ->where('demo.email', 'demo@pablomandile.com.ar')
            ->where('demo.password', 'demo1234'));

        config(['demo.enabled' => false]);
        $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('demo', null));
        $this->get('/register')->assertOk();
    }

    public function test_reset_refuses_to_run_outside_the_demo(): void
    {
        config(['demo.enabled' => false]);
        $user = User::factory()->create();

        $this->artisan('demo:reset')->assertFailed();
        $this->assertModelExists($user);
    }
}

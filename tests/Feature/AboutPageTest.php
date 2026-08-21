<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_about_page_renders_for_a_logged_in_user(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/about')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('About'));
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/about')->assertRedirect('/login');
    }
}

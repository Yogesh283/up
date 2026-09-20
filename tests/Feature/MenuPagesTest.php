<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_all_menu_pages(): void
    {
        $user = User::factory()->create();

        $pages = [
            '/dashboard',
            '/history',
            '/results',
            '/referral',
            '/deposit',
            '/withdrawal',
        ];

        foreach ($pages as $page) {
            $response = $this->actingAs($user)->get($page);

            $response->assertOk();
        }
    }

    public function test_guest_is_redirected_from_menu_pages_to_login(): void
    {
        $pages = [
            '/dashboard',
            '/history',
            '/results',
            '/referral',
            '/deposit',
            '/withdrawal',
        ];

        foreach ($pages as $page) {
            $this->get($page)->assertRedirect('/login');
        }
    }
}

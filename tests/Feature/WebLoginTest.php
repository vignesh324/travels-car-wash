<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_rendered_with_the_blade_form(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertViewIs('auth.login')
            ->assertSee('Sign in to your account')
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false);
    }

    public function test_valid_credentials_sign_in_and_render_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->followingRedirects()
            ->post(route('login'), [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertOk()
            ->assertSee('Dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_dropdown_pages_and_forms_load_their_controller_data(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ([
            'trips.index',
            'invoices.index',
            'payments.index',
            'expenses.index',
            'car-wash.index',
        ] as $routeName) {
            $this->get(route($routeName))->assertOk();
        }

        foreach ([
            route('trips.addEdit'),
            route('invoices.addEdit'),
            route('payments.addEdit'),
            route('expenses.addEdit', ['type' => 'vehicle']),
            route('car-wash.addEdit', ['type' => 'transaction']),
        ] as $url) {
            $this->getJson($url)->assertOk();
        }
    }

    public function test_login_session_authenticates_same_origin_api_requests(): void
    {
        $user = User::factory()->create();

        $this->followingRedirects()
            ->post(route('login'), [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertOk()
            ->assertSee('Dashboard');

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_invalid_credentials_return_to_the_blade_login_form_with_an_error(): void
    {
        $user = User::factory()->create();

        $this->from(route('login'))
            ->post(route('login'), [
                'email' => $user->email,
                'password' => 'incorrect-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->get(route('login'))
            ->assertOk()
            ->assertViewIs('auth.login')
            ->assertSee('Sign in to your account');
    }
}

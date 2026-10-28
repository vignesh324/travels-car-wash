<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_pages_include_mobile_navigation_and_quick_actions(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertSee('id="mobile-bottom-nav"', false)
            ->assertSee('id="mobile-quick-action-toggle"', false)
            ->assertSee('id="mobile-more-menu"', false)
            ->assertSee('Dashboard')
            ->assertSee('Trips')
            ->assertSee('Vehicle Expenses')
            ->assertSee('Business Expenses')
            ->assertSee('Settings')
            ->assertSee(route('trips.index').'?action=new', false)
            ->assertSee(route('quick-trip-executions.index'), false)
            ->assertSee(route('expenses.vehicle.index').'?action=new', false)
            ->assertSee(route('invoices.index').'?action=new', false);

        foreach ([
            'vehicles.index',
            'customers.index',
            'expenses.vehicle.index',
            'expenses.business.index',
            'invoices.index',
            'payments.index',
            'car-wash.index',
            'reports.index',
            'activity-logs.index',
            'settings.index',
        ] as $routeName) {
            $response->assertSee(route($routeName), false);
        }

        $response->assertSee('aria-label="Main navigation"', false)
            ->assertSee('id="sidebar-toggle"', false)
            ->assertSee('id="account-menu-toggle"', false)
            ->assertSee(route('profile.index'), false)
            ->assertSee('method="POST" action="'.route('logout').'"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('class="app-footer d-none d-md-flex', false)
            ->assertSee('id="mobile-business-name"', false);
        $response->assertSee('id="toast-container" class="position-fixed top-0 end-0 mt-5 p-3 z-3 pe-none"', false)
            ->assertSee('width: 44px; height: 44px;', false);
        $this->assertStringContainsString('toast align-items-center border-0 pe-auto', file_get_contents(public_path('js/common.js')));
    }

    public function test_quick_trip_execution_has_its_own_navigation_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('trips.quick-execution'))
            ->assertRedirect(route('trips.index'));

        $executionPage = $this->get(route('trips.index'))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression(
            '/<a href="'.preg_quote(route('trips.quick-execution'), '/').'"/',
            $executionPage
        );
        $this->assertStringNotContainsString('data-action="trips.quick-execution"', $executionPage);
        $this->assertStringNotContainsString('js/quick-trip-execution.js', $executionPage);
        $this->assertStringContainsString(route('quick-trip-executions.index'), $executionPage);

        $executionIndex = $this->get(route('quick-trip-executions.index'))
            ->assertOk()
            ->assertSee('quick-trip-executions-dt')
            ->assertSee('New Quick Trip Execution')
            ->getContent();
        $this->assertMatchesRegularExpression(
            '/<a href="'.preg_quote(route('quick-trip-executions.index'), '/').'"[^>]*aria-current="page"/',
            $executionIndex
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<a href="'.preg_quote(route('trips.index'), '/').'"[^>]*aria-current="page"/',
            $executionIndex
        );

        $configurationPage = $this->get(route('customers.quick-trips'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<a href="'.preg_quote(route('customers.quick-trips'), '/').'"[^>]*aria-current="page"/',
            $configurationPage
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<a href="'.preg_quote(route('customers.index'), '/').'"[^>]*aria-current="page"/',
            $configurationPage
        );
    }

    public function test_mobile_table_actions_hide_labels_and_keep_touch_targets(): void
    {
        $styles = file_get_contents(public_path('css/app-shell.css'));

        $this->assertStringContainsString('.resource-row-actions > .btn > span {', $styles);
        $this->assertStringContainsString('min-width: 44px;', $styles);
        $this->assertStringContainsString('min-height: 44px;', $styles);
        $this->assertStringContainsString('flex-wrap: wrap;', $styles);
    }

    public function test_mobile_header_uses_business_name_when_configured(): void
    {
        $user = User::factory()->create(['name' => 'Account Owner']);
        BusinessProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Northwind Travel Co.',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertSee('title="Northwind Travel Co."', false)
            ->assertSee('>Northwind Travel Co.</span>', false);
    }

    public function test_mobile_header_falls_back_to_user_name_without_business_profile(): void
    {
        $user = User::factory()->create(['name' => 'Account Owner']);

        $this->actingAs($user)
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertSee('id="mobile-business-name"', false)
            ->assertSee('title="Account Owner"', false)
            ->assertSee('>Account Owner</span>', false);
    }
}

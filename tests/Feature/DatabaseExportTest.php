<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_export_requires_authentication(): void
    {
        $this->get(route('database.export'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_download_a_sql_database_export(): void
    {
        $user = User::factory()->create(['email' => 'backup-example@example.com']);

        $response = $this->actingAs($user)->get(route('database.export'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/sql; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename=travels-car-wash-'.now()->format('Y-m-d-His').'.sql');

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $contents = $response->streamedContent();

        $this->assertStringContainsString('CREATE TABLE "users"', $contents);
        $this->assertStringContainsString('backup-example@example.com', $contents);

        $this->get(route('settings.index'))
            ->assertOk()
            ->assertDontSee(route('database.export'));
    }
}

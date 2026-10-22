<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\User;
use App\Services\BusinessProfileLogoStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_settings_can_be_saved_without_uploading_a_logo(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('settings.update'), ['business_name' => 'Sample Travels'])
            ->assertOk()
            ->assertJsonPath('business_name', 'Sample Travels')
            ->assertJsonPath('logo', null);

        $this->assertDatabaseHas('business_profiles', [
            'user_id' => $user->id,
            'business_name' => 'Sample Travels',
            'logo' => null,
        ]);
    }

    public function test_business_logo_upload_is_stored_and_replaces_existing_logo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $oldLogo = 'logos/old-logo.png';
        Storage::disk('public')->put($oldLogo, 'old logo');
        BusinessProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Sample Travels',
            'logo' => $oldLogo,
        ]);

        $response = $this->actingAs($user)
            ->put(route('settings.update'), [
                'business_name' => 'Updated Travels',
                'logo' => UploadedFile::fake()->image('business-logo.png'),
            ])
            ->assertOk()
            ->assertJsonPath('business_name', 'Updated Travels');

        $logoPath = $response->json('logo');
        $this->assertIsString($logoPath);
        $this->assertNotSame('', $logoPath);
        $this->assertStringEndsWith('/storage/'.$logoPath, $response->json('logo_url'));
        Storage::disk('public')->assertExists($logoPath);
        Storage::disk('public')->assertMissing($oldLogo);
    }

    public function test_logo_upload_is_stored_when_realpath_is_empty_but_upload_path_is_readable(): void
    {
        Storage::fake('public');
        $temporaryLogo = UploadedFile::fake()->image('business-logo.png');
        $logoWithEmptyRealPath = new class(
            $temporaryLogo->getPathname(),
            'business-logo.png',
            'image/png',
            UPLOAD_ERR_OK,
            true
        ) extends UploadedFile {
            public function getRealPath(): string|false
            {
                return '';
            }
        };

        $response = $this->actingAs(User::factory()->create())
            ->putJson(route('settings.update'), [
                'business_name' => 'Sample Travels',
                'logo' => $logoWithEmptyRealPath,
            ])
            ->assertOk();

        Storage::disk('public')->assertExists($response->json('logo'));
    }

    public function test_logo_upload_with_an_unreadable_path_returns_a_validation_error(): void
    {
        $temporaryLogo = UploadedFile::fake()->image('business-logo.png');
        $logoWithEmptyPath = new class(
            $temporaryLogo->getPathname(),
            'business-logo.png',
            'image/png',
            UPLOAD_ERR_OK,
            true
        ) extends UploadedFile {
            public function getPathname(): string
            {
                return '';
            }
        };

        $this->expectException(ValidationException::class);

        app(BusinessProfileLogoStorage::class)->store($logoWithEmptyPath);
    }

    public function test_business_profile_api_upload_is_stored(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->post('/api/settings/business', [
                'business_name' => 'API Travels',
                'logo' => UploadedFile::fake()->image('api-logo.png'),
            ])
            ->assertOk()
            ->assertJsonPath('business_name', 'API Travels');

        Storage::disk('public')->assertExists($response->json('logo'));
    }
}

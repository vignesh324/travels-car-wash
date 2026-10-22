<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\User;
use App\Services\BusinessProfileLogoStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
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

    public function test_profile_page_renders_personal_profile_and_password_forms(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.index'))
            ->assertOk()
            ->assertSee('id="account-form"', false)
            ->assertSee('id="password-form"', false)
            ->assertSee('value="'.$user->name.'"', false)
            ->assertSee('Change password');
    }

    public function test_business_settings_page_does_not_render_personal_account_forms(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Business profile')
            ->assertSee('Invoice and receipt defaults')
            ->assertDontSee('Personal profile')
            ->assertDontSee('id="account-form"', false)
            ->assertDontSee('id="password-form"', false);
    }

    public function test_business_settings_save_updates_the_mobile_header_name(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('id="mobile-business-name"', false)
            ->assertSee('mobileBusinessName.textContent = data.business_name;', false);
    }

    public function test_authenticated_user_can_update_their_personal_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson(route('settings.account.update'), [
                'name' => 'Morgan Lee',
                'email' => 'morgan@example.test',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Personal profile saved.')
            ->assertJsonPath('user.name', 'Morgan Lee')
            ->assertJsonPath('user.email', 'morgan@example.test');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Morgan Lee',
            'email' => 'morgan@example.test',
        ]);
    }

    public function test_personal_profile_rejects_an_email_used_by_another_user(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        $this->actingAs($user)
            ->putJson(route('settings.account.update'), [
                'name' => 'Updated Name',
                'email' => $anotherUser->email,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_authenticated_user_can_change_their_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson(route('settings.password.update'), [
                'current_password' => 'password',
                'new_password' => 'new-password-123',
                'new_password_confirmation' => 'new-password-123',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Password changed successfully.');

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_password_change_rejects_an_incorrect_current_password(): void
    {
        $user = User::factory()->create();
        $originalHash = $user->password;

        $this->actingAs($user)
            ->putJson(route('settings.password.update'), [
                'current_password' => 'incorrect-password',
                'new_password' => 'new-password-123',
                'new_password_confirmation' => 'new-password-123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertSame($originalHash, $user->fresh()->password);
    }

    public function test_personal_profile_update_preserves_existing_business_settings(): void
    {
        $user = User::factory()->create();
        BusinessProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Travels and Tours',
            'email' => 'office@example.test',
            'invoice_prefix' => 'TRV',
        ]);

        $this->actingAs($user)
            ->putJson(route('settings.account.update'), [
                'name' => 'Account Owner',
                'email' => 'owner@example.test',
            ])
            ->assertOk();

        $this->assertDatabaseHas('business_profiles', [
            'user_id' => $user->id,
            'business_name' => 'Travels and Tours',
            'email' => 'office@example.test',
            'invoice_prefix' => 'TRV',
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

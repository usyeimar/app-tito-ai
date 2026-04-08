<?php

use App\Models\Central\Auth\Authentication\CentralUser;
use App\Models\Central\Auth\SocialLogin\SocialAccount;
use App\Services\Central\Auth\Authentication\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery\MockInterface;
use SocialiteProviders\Microsoft\Provider as MicrosoftProvider;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.microsoft.client_id', 'microsoft-client-id');
});

it('logs in an existing user linked by microsoft social account', function (): void {
    $user = CentralUser::factory()->create();

    SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'microsoft',
        'provider_user_id' => 'microsoft-sub-123',
        'email' => $user->email,
    ]);

    $this->mock(AuthService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('authenticate')
            ->once()
            ->andReturn([
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]);
    });

    $socialiteUser = (new SocialiteUser)
        ->setRaw(['mail' => 'existing@workupcloud.test'])
        ->map([
            'id' => 'microsoft-sub-123',
            'name' => 'Microsoft User',
            'email' => $user->email,
        ]);

    $microsoftProvider = Mockery::mock(MicrosoftProvider::class);
    $microsoftProvider->shouldReceive('userFromToken')
        ->once()
        ->with('valid-access-token')
        ->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')
        ->once()
        ->with('microsoft')
        ->andReturn($microsoftProvider);

    $response = $this->postJson('/api/auth/microsoft', [
        'access_token' => 'valid-access-token',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.access_token', 'access-token')
        ->assertJsonPath('data.refresh_token', 'refresh-token');
});

it('creates a verified user and links microsoft social account', function (): void {
    $this->mock(AuthService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('authenticate')
            ->once()
            ->andReturn([
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]);
    });

    $socialiteUser = (new SocialiteUser)
        ->setRaw(['mail' => 'new-microsoft@workupcloud.test'])
        ->map([
            'id' => 'microsoft-sub-456',
            'name' => 'New Microsoft User',
            'email' => 'new-microsoft@workupcloud.test',
        ]);

    $microsoftProvider = Mockery::mock(MicrosoftProvider::class);
    $microsoftProvider->shouldReceive('userFromToken')
        ->once()
        ->with('new-access-token')
        ->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')
        ->once()
        ->with('microsoft')
        ->andReturn($microsoftProvider);

    $response = $this->postJson('/api/auth/microsoft', [
        'access_token' => 'new-access-token',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.user.email', 'new-microsoft@workupcloud.test');

    $user = CentralUser::query()->where('email', 'new-microsoft@workupcloud.test')->first();

    expect($user)->not->toBeNull();
    expect($user?->email_verified_at)->not->toBeNull();

    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $user?->id,
        'provider' => 'microsoft',
        'provider_user_id' => 'microsoft-sub-456',
        'email' => 'new-microsoft@workupcloud.test',
    ]);
});

it('returns tfa required payload for microsoft user with two factor enabled', function (): void {
    $user = CentralUser::factory()->create([
        'two_factor_enabled' => true,
    ]);

    SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'microsoft',
        'provider_user_id' => 'microsoft-sub-tfa',
        'email' => $user->email,
    ]);

    $this->mock(AuthService::class, function (MockInterface $mock) use ($user): void {
        $mock->shouldReceive('startTfaChallenge')
            ->once()
            ->andReturn([
                'kind' => 'tfa_required',
                'user' => $user,
                'tfa_required' => true,
                'tfa_token' => 'tfa-session-token',
            ]);
    });

    $socialiteUser = (new SocialiteUser)
        ->setRaw(['mail' => $user->email])
        ->map([
            'id' => 'microsoft-sub-tfa',
            'name' => 'TFA Microsoft User',
            'email' => $user->email,
        ]);

    $microsoftProvider = Mockery::mock(MicrosoftProvider::class);
    $microsoftProvider->shouldReceive('userFromToken')->once()->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->once()->with('microsoft')->andReturn($microsoftProvider);

    $response = $this->postJson('/api/auth/microsoft', [
        'access_token' => 'token-with-tfa-user',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.tfa_required', true)
        ->assertJsonPath('data.tfa_token', 'tfa-session-token')
        ->assertJsonMissingPath('data.access_token');
});

it('returns validation error when microsoft access token is missing', function (): void {
    $response = $this->postJson('/api/auth/microsoft', []);

    $response->assertStatus(422)
        ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR')
        ->assertJsonPath('errors.0.source.pointer', 'access_token');
});

it('rejects legacy microsoft id token-only payload after hard switch', function (): void {
    $response = $this->postJson('/api/auth/microsoft', [
        'id_token' => 'legacy-id-token',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.0.source.pointer', 'access_token');
});

it('returns validation error when microsoft access token is invalid', function (): void {
    $this->mock(AuthService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('authenticate');
        $mock->shouldNotReceive('startTfaChallenge');
    });

    $microsoftProvider = Mockery::mock(MicrosoftProvider::class);
    $microsoftProvider->shouldReceive('userFromToken')
        ->once()
        ->with('invalid-token')
        ->andThrow(new RuntimeException('invalid token'));

    Socialite::shouldReceive('driver')
        ->once()
        ->with('microsoft')
        ->andReturn($microsoftProvider);

    $response = $this->postJson('/api/auth/microsoft', [
        'access_token' => 'invalid-token',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.0.source.pointer', 'access_token')
        ->assertJsonPath('errors.0.detail', 'Invalid access token.');
});

it('rejects microsoft social login when email is missing from provider data', function (): void {
    $this->mock(AuthService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('authenticate');
        $mock->shouldNotReceive('startTfaChallenge');
    });

    $socialiteUser = (new SocialiteUser)
        ->setRaw([])
        ->map([
            'id' => 'microsoft-sub-no-email',
            'name' => 'No Email User',
            'email' => null,
        ]);

    $microsoftProvider = Mockery::mock(MicrosoftProvider::class);
    $microsoftProvider->shouldReceive('userFromToken')->once()->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->once()->with('microsoft')->andReturn($microsoftProvider);

    $response = $this->postJson('/api/auth/microsoft', [
        'access_token' => 'token-missing-email',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.0.source.pointer', 'access_token')
        ->assertJsonPath('errors.0.detail', 'Social account email is required.');
});

it('omits token fields from json and sets cookies in microsoft cookie auth mode', function (): void {
    config()->set('cors.allowed_origins', ['http://localhost:3001']);

    $user = CentralUser::factory()->create();

    SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'microsoft',
        'provider_user_id' => 'microsoft-sub-cookie',
        'email' => $user->email,
    ]);

    $this->mock(AuthService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('authenticate')
            ->once()
            ->andReturn([
                'access_token' => 'cookie-access-token',
                'refresh_token' => 'cookie-refresh-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]);
    });

    $socialiteUser = (new SocialiteUser)
        ->setRaw(['mail' => $user->email])
        ->map([
            'id' => 'microsoft-sub-cookie',
            'name' => 'Cookie Microsoft User',
            'email' => $user->email,
        ]);

    $microsoftProvider = Mockery::mock(MicrosoftProvider::class);
    $microsoftProvider->shouldReceive('userFromToken')->once()->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->once()->with('microsoft')->andReturn($microsoftProvider);

    $response = $this->withHeaders([
        'X-Auth-Mode' => 'cookie',
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ])->postJson('/api/auth/microsoft', [
        'access_token' => 'cookie-token',
    ]);

    $response->assertOk()
        ->assertJsonMissingPath('data.access_token')
        ->assertJsonMissingPath('data.refresh_token')
        ->assertJsonPath('data.expires_in', 3600)
        ->assertCookie(config('passport_tokens.access_cookie.central_name', 'central_access_token'))
        ->assertCookie(config('passport_tokens.refresh_cookie.central_name', 'central_refresh_token'));
});

<?php

use App\Models\Central\Auth\Authentication\CentralUser;
use App\Models\Central\Auth\SocialLogin\SocialAccount;
use App\Services\Central\Auth\Authentication\AuthService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.google.client_id', 'google-client-id');
});

it('logs in an existing user linked by social account', function (): void {
    $user = CentralUser::factory()->create();

    SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_user_id' => 'google-sub-123',
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
        ->setRaw(['email_verified' => true])
        ->map([
            'id' => 'google-sub-123',
            'name' => 'Google User',
            'email' => $user->email,
        ]);

    $googleProvider = Mockery::mock(GoogleProvider::class);
    $googleProvider->shouldReceive('userFromToken')
        ->once()
        ->with('valid-access-token')
        ->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')
        ->once()
        ->with('google')
        ->andReturn($googleProvider);

    $response = $this->postJson('/api/auth/google', [
        'access_token' => 'valid-access-token',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.access_token', 'access-token')
        ->assertJsonPath('data.refresh_token', 'refresh-token');
});

it('creates a verified user and links social account for first-time google login', function (): void {
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
        ->setRaw(['email_verified' => true])
        ->map([
            'id' => 'google-sub-456',
            'name' => 'New Google User',
            'email' => 'new-user@workupcloud.test',
        ]);

    $googleProvider = Mockery::mock(GoogleProvider::class);
    $googleProvider->shouldReceive('userFromToken')
        ->once()
        ->with('new-access-token')
        ->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')
        ->once()
        ->with('google')
        ->andReturn($googleProvider);

    $response = $this->postJson('/api/auth/google', [
        'access_token' => 'new-access-token',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.user.email', 'new-user@workupcloud.test');

    $user = CentralUser::query()->where('email', 'new-user@workupcloud.test')->first();

    expect($user)->not->toBeNull();
    expect($user?->email_verified_at)->not->toBeNull();

    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $user?->id,
        'provider' => 'google',
        'provider_user_id' => 'google-sub-456',
        'email' => 'new-user@workupcloud.test',
    ]);
});

it('returns tfa required payload when user has two factor enabled', function (): void {
    $user = CentralUser::factory()->create([
        'two_factor_enabled' => true,
    ]);

    SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_user_id' => 'google-sub-tfa',
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
        ->setRaw(['email_verified' => true])
        ->map([
            'id' => 'google-sub-tfa',
            'name' => 'TFA User',
            'email' => $user->email,
        ]);

    $googleProvider = Mockery::mock(GoogleProvider::class);
    $googleProvider->shouldReceive('userFromToken')->once()->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($googleProvider);

    $response = $this->postJson('/api/auth/google', [
        'access_token' => 'token-with-tfa-user',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.tfa_required', true)
        ->assertJsonPath('data.tfa_token', 'tfa-session-token')
        ->assertJsonMissingPath('data.access_token');
});

it('returns validation error when access token is missing', function (): void {
    $response = $this->postJson('/api/auth/google', []);

    $response->assertStatus(422)
        ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR')
        ->assertJsonPath('errors.0.source.pointer', 'access_token');
});

it('returns validation error when google access token is invalid', function (): void {
    $this->mock(AuthService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('authenticate');
        $mock->shouldNotReceive('startTfaChallenge');
    });

    $googleProvider = Mockery::mock(GoogleProvider::class);
    $googleProvider->shouldReceive('userFromToken')
        ->once()
        ->with('invalid-token')
        ->andThrow(new RuntimeException('invalid token'));

    Socialite::shouldReceive('driver')
        ->once()
        ->with('google')
        ->andReturn($googleProvider);

    $response = $this->postJson('/api/auth/google', [
        'access_token' => 'invalid-token',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.0.source.pointer', 'access_token')
        ->assertJsonPath('errors.0.detail', 'Invalid access token.');
});

it('rejects unverified social email and sends verification for existing local user', function (): void {
    Notification::fake();

    $existingUser = CentralUser::factory()->unverified()->create([
        'email' => 'pending@workupcloud.test',
    ]);

    $this->mock(AuthService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('authenticate');
        $mock->shouldNotReceive('startTfaChallenge');
    });

    $socialiteUser = (new SocialiteUser)
        ->setRaw(['email_verified' => false])
        ->map([
            'id' => 'google-sub-unverified',
            'name' => 'Pending User',
            'email' => 'pending@workupcloud.test',
        ]);

    $googleProvider = Mockery::mock(GoogleProvider::class);
    $googleProvider->shouldReceive('userFromToken')->once()->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($googleProvider);

    $response = $this->postJson('/api/auth/google', [
        'access_token' => 'pending-token',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.0.source.pointer', 'access_token')
        ->assertJsonPath('errors.0.detail', 'Email address is not verified.');

    Notification::assertSentTo($existingUser, VerifyEmail::class);
    $this->assertDatabaseCount('social_accounts', 0);
});

it('omits token fields from json and sets cookies in cookie auth mode', function (): void {
    config()->set('cors.allowed_origins', ['http://localhost:3001']);

    $user = CentralUser::factory()->create();

    SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_user_id' => 'google-sub-cookie',
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
        ->setRaw(['email_verified' => true])
        ->map([
            'id' => 'google-sub-cookie',
            'name' => 'Cookie User',
            'email' => $user->email,
        ]);

    $googleProvider = Mockery::mock(GoogleProvider::class);
    $googleProvider->shouldReceive('userFromToken')->once()->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($googleProvider);

    $response = $this->withHeaders([
        'X-Auth-Mode' => 'cookie',
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ])->postJson('/api/auth/google', [
        'access_token' => 'cookie-token',
    ]);

    $response->assertOk()
        ->assertJsonMissingPath('data.access_token')
        ->assertJsonMissingPath('data.refresh_token')
        ->assertJsonPath('data.expires_in', 3600)
        ->assertCookie(config('passport_tokens.access_cookie.central_name', 'central_access_token'))
        ->assertCookie(config('passport_tokens.refresh_cookie.central_name', 'central_refresh_token'));
});

<?php

use App\Services\Central\Auth\Authentication\AuthService;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;

it('returns a form pointer for login request validation errors', function (): void {
    $response = $this->postJson('/api/auth/login', []);

    $response->assertStatus(422)
        ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');

    $pointers = collect($response->json('errors', []))
        ->pluck('source.pointer')
        ->unique()
        ->values()
        ->all();

    expect($pointers)->toBe(['_form']);
});

it('returns a form pointer for invalid login credentials', function (): void {
    $this->mock(AuthService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('login')
            ->once()
            ->andThrow(ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]));
    });

    $response = $this->postJson('/api/auth/login', [
        'email' => 'invalid-login-pointer@workupcloud.test',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR')
        ->assertJsonPath('errors.0.source.pointer', '_form')
        ->assertJsonPath('errors.0.detail', 'The provided credentials are incorrect.');
});

<?php

it('returns a JSON unauthenticated error for API requests without an explicit JSON accept header', function (): void {
    $response = $this->get('/api/auth/me', [
        'Accept' => '*/*',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('errors.0.code', 'UNAUTHENTICATED')
        ->assertJsonPath('errors.0.title', 'Unauthenticated');
});

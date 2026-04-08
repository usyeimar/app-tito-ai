<?php

use App\Models\Tenant\CRM\Contacts\Contact;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Client;
use Tests\TenantTestCase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TenantTestCase::class)->in('Tenant');

beforeEach(function () {
    config()->set('passport_clients.central.client_id', '1');
    config()->set('passport_clients.central.client_secret', 'test-central-secret');
    config()->set('passport_clients.tenant.client_id', '1');
    config()->set('passport_clients.tenant.client_secret', 'test-tenant-secret');

    if (Schema::hasTable('oauth_clients')) {
        Client::query()->updateOrCreate(
            ['id' => 1],
            [
                'user_id' => null,
                'name' => 'Test Central Password Grant',
                'secret' => 'test-central-secret',
                'provider' => 'central_users',
                'redirect' => 'http://localhost',
                'personal_access_client' => false,
                'password_client' => true,
                'revoked' => false,
            ],
        );
    }

    $privateKey = storage_path('oauth-private.key');
    $publicKey = storage_path('oauth-public.key');

    if (! file_exists($privateKey) || ! file_exists($publicKey)) {
        Artisan::call('passport:keys', ['--force' => true]);
    }
});

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function createContact(): Contact
{
    $contact = Contact::factory()->make();
    $contact->save();

    return $contact;
}

function assertHasValidationError($response, string $pointer): void
{
    $response->assertUnprocessable();
    $errors = $response->json('errors');

    $found = collect($errors)->contains(fn ($error) => ($error['source']['pointer'] ?? null) === $pointer);

    expect($found)->toBeTrue("Expected validation error for pointer '{$pointer}' not found in response.");
}

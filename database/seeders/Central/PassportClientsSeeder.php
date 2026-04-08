<?php

namespace Database\Seeders\Central;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Client;

class PassportClientsSeeder extends Seeder
{
    public function run(): void
    {
        $clientId = (string) config('passport_clients.central.client_id');
        $clientSecret = (string) config('passport_clients.central.client_secret');

        if ($clientId === '' || $clientSecret === '') {
            return;
        }

        $client = Client::find($clientId);

        $columns = Schema::getColumnListing((new Client)->getTable());

        $attributes = [
            'name' => 'Central Password Grant',
            'secret' => $clientSecret,
            'provider' => 'central_users',
            'revoked' => false,
        ];

        if (in_array('redirect', $columns, true)) {
            $attributes['redirect'] = 'http://localhost';
        }

        if (in_array('redirect_uris', $columns, true)) {
            $attributes['redirect_uris'] = ['http://localhost'];
        }

        if (in_array('grant_types', $columns, true)) {
            $attributes['grant_types'] = ['password', 'refresh_token'];
        }

        if (in_array('personal_access_client', $columns, true)) {
            $attributes['personal_access_client'] = false;
        }

        if (in_array('password_client', $columns, true)) {
            $attributes['password_client'] = true;
        }

        if ($client) {
            $client->forceFill($attributes)->save();

            return;
        }

        $client = new Client;
        $client->forceFill([
            'id' => (string) $clientId,
            'user_id' => null,
            ...$attributes,
        ])->save();
    }
}

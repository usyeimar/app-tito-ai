<?php

declare(strict_types=1);

use App\Models\Tenant\Agent\Agent;

return [
    'types' => [
        'agent' => [
            'model' => Agent::class,
            'label_fields' => ['name', 'email'],
        ],
    ],

    'redaction' => [
        'default' => [
            'enabled' => false,
            'fields' => [],
        ],
        'modules' => [],
    ],
];

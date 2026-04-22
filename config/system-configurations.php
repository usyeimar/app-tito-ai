<?php

return [
    'defaults' => [
        [
            'key' => 'aws_ses',
            'label' => 'AWS SES',
            'data' => null,
            'meta' => [
                'is_active' => false,
                'last_validated_at' => null,
                'last_validation_error' => null,
            ],
        ],
        [
            'key' => 'allowed_email_addresses',
            'label' => 'Allowed Email Addresses',
            'data' => null,
            'meta' => [
                'is_active' => false,
                'last_validated_at' => null,
                'last_validation_error' => null,
            ],
        ],
        [
            'key' => 'documenso',
            'label' => 'Documenso',
            'data' => null,
            'meta' => [
                'is_active' => false,
                'last_validated_at' => null,
                'last_validation_error' => null,
            ],
        ],
        [
            'key' => 'assistant_defaults',
            'label' => 'Assistant Defaults',
            'data' => [
                'llm_provider' => 'openai',
                'llm_model' => 'gpt-4o',
                'voice_id' => null,
                'language' => 'es-CO',
                'stt_provider' => 'deepgram',
                'tts_provider' => 'elevenlabs',
            ],
            'meta' => [
                'description' => 'Default configuration for AI assistants when not explicitly set',
            ],
        ],
    ],
];

<?php

declare(strict_types=1);

namespace App\Services\Tenant\Agent\Runner;

use App\Models\Tenant\Agent\Agent;
use Illuminate\Support\Arr;

/**
 * Translates a tenant Agent (with its settings + tools) into the
 * AgentConfig payload expected by the FastAPI runner.
 *
 * @see services/runners/app/schemas/agent.py
 */
final class AgentConfigBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Agent $agent): array
    {
        $agent->loadMissing(['settings', 'tools']);

        $brain = (array) ($agent->settings?->brain_config ?? []);
        $runtime = (array) ($agent->settings?->runtime_config ?? []);
        $architecture = (array) ($agent->settings?->architecture_config ?? []);
        $capabilities = (array) ($agent->settings?->capabilities_config ?? []);
        $observability = (array) ($agent->settings?->observability_config ?? []);

        $tenantId = (string) (tenant('id') ?? 'central');

        return [
            'version' => '1.0.0',
            'agent_id' => (string) $agent->id,
            'tenant_id' => $tenantId,
            'callback_url' => $this->callbackUrl($tenantId, $agent),
            'metadata' => [
                'name' => (string) $agent->name,
                'slug' => (string) $agent->slug,
                'description' => (string) ($agent->description ?? ''),
                'tags' => array_values((array) ($agent->tags ?? [])),
                'language' => (string) $agent->language,
            ],
            'brain' => $this->brainPayload($brain, $agent),
            'runtime_profiles' => $this->runtimePayload($runtime, $agent),
            'architecture' => $architecture !== [] ? $architecture : null,
            'capabilities' => $this->capabilitiesPayload($capabilities, $agent),
            'observability' => $this->observabilityPayload($observability),
        ];
    }

    /**
     * @param  array<string, mixed>  $brain
     * @return array<string, mixed>
     */
    private function brainPayload(array $brain, Agent $agent): array
    {
        $llm = (array) Arr::get($brain, 'llm', []);

        $payload = [
            'llm' => [
                'provider' => (string) Arr::get($llm, 'provider', Arr::get($brain, 'provider', 'openai')),
                'model' => (string) Arr::get($llm, 'model', Arr::get($brain, 'model', 'gpt-4o-mini')),
                'config' => [
                    'temperature' => (float) Arr::get(
                        $llm,
                        'config.temperature',
                        Arr::get($brain, 'temperature', 0.5),
                    ),
                    'max_tokens' => (int) Arr::get(
                        $llm,
                        'config.max_tokens',
                        Arr::get($brain, 'max_tokens', 1024),
                    ),
                    'top_p' => (float) Arr::get(
                        $llm,
                        'config.top_p',
                        Arr::get($brain, 'top_p', 0.9),
                    ),
                ],
                'instructions' => (string) Arr::get(
                    $llm,
                    'instructions',
                    Arr::get($brain, 'system_prompt', 'You are a helpful assistant.'),
                ),
            ],
            'localization' => [
                'default_locale' => (string) $agent->language,
                'timezone' => (string) $agent->timezone,
                'currency' => (string) $agent->currency,
                'number_format' => (string) $agent->number_format,
            ],
            'context' => Arr::get($brain, 'context', [
                'strategy' => 'none',
                'max_tokens' => 4000,
                'min_messages' => 4,
                'enabled' => false,
            ]),
        ];

        // knowledge_base — explicit from brain_config or inferred from agent relation
        $knowledgeBase = Arr::get($brain, 'knowledge_base');
        if ($knowledgeBase !== null) {
            $payload['knowledge_base'] = $knowledgeBase;
        } elseif ($agent->knowledge_base_id !== null) {
            $payload['knowledge_base'] = ['id' => $agent->knowledge_base_id];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $runtime
     * @return array<string, mixed>
     */
    private function runtimePayload(array $runtime, Agent $agent): array
    {
        $stt = (array) Arr::get($runtime, 'stt', []);
        $tts = (array) Arr::get($runtime, 'tts', []);
        $transport = (array) Arr::get($runtime, 'transport', []);
        $behavior = (array) Arr::get($runtime, 'behavior', []);

        $allowed = (array) config('runners.allowed_transports', ['livekit', 'daily']);
        $requested = (string) Arr::get(
            $transport,
            'provider',
            config('runners.default_transport', 'livekit'),
        );
        $resolvedTransport = in_array($requested, $allowed, true)
            ? $requested
            : (string) config('runners.default_transport', 'livekit');

        return [
            'stt' => [
                'provider' => (string) Arr::get($stt, 'provider', 'deepgram'),
                'model' => (string) Arr::get($stt, 'model', 'nova-2'),
                'language' => (string) Arr::get($stt, 'language', $agent->language),
            ],
            'tts' => [
                'provider' => (string) Arr::get($tts, 'provider', 'cartesia'),
                'voice_id' => (string) Arr::get(
                    $tts,
                    'voice_id',
                    '79a125e8-cd45-4c13-8a67-188112f4dd22',
                ),
            ],
            'transport' => [
                'provider' => $resolvedTransport,
            ],
            'behavior' => [
                'interruptibility' => (bool) Arr::get($behavior, 'interruptibility', true),
                'initial_action' => (string) Arr::get($behavior, 'initial_action', 'SPEAK_FIRST'),
                'streaming' => (bool) Arr::get($behavior, 'streaming', true),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $capabilities
     * @return array<string, mixed>
     */
    private function capabilitiesPayload(array $capabilities, Agent $agent): array
    {
        $configuredTools = (array) Arr::get($capabilities, 'tools', []);

        if ($configuredTools === []) {
            $configuredTools = $agent->tools
                ->map(fn ($tool) => [
                    'name' => $tool->name,
                    'description' => $tool->description,
                    'parameters' => $tool->parameters ?? null,
                    'disabled' => (bool) ($tool->disabled ?? false),
                ])
                ->all();
        }

        return [
            'tools' => array_values($configuredTools),
        ];
    }

    /**
     * @param  array<string, mixed>  $observability
     * @return array<string, mixed>|null
     */
    private function observabilityPayload(array $observability): ?array
    {
        if ($observability === []) {
            return null;
        }

        return [
            'log_level' => (string) Arr::get($observability, 'log_level', 'INFO'),
            'metrics_enabled' => (bool) Arr::get($observability, 'metrics_enabled', false),
        ];
    }

    private function callbackUrl(string $tenantId, Agent $agent): ?string
    {
        $configured = config('runners.callback_url');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        // Future: tenant-aware webhook endpoint. For now leave null so the
        // runner falls back to its own BACKEND_URL setting.
        return null;
    }
}

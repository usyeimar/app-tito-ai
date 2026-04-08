<?php

use App\Models\Tenant\Activity\ActivityEvent;
use App\Models\Tenant\Commons\Files\File;
use App\Models\Tenant\Commons\Files\FileFolder;
use App\Models\Tenant\CRM\Leads\Lead;

// ── Helpers ─────────────────────────────────────────────────────────────────

function activityLead(): Lead
{
    return Lead::factory()->create();
}

function activityFile(Lead $lead, array $attrs = []): File
{
    return File::factory()->forFileable($lead)->create($attrs);
}

function activityFolder(Lead $lead, array $attrs = []): FileFolder
{
    return FileFolder::factory()->forFileable($lead)->create($attrs);
}

function latestActivityForSubject(string $subjectType, string $subjectId): ?ActivityEvent
{
    return ActivityEvent::query()
        ->where('subject_type', $subjectType)
        ->where('subject_id', $subjectId)
        ->latest('id')
        ->first();
}

dataset('activityScenarios', [
    'rename file' => [[
        'setup' => function (): array {
            $lead = activityLead();

            return [
                'lead' => $lead,
                'file' => activityFile($lead, ['name' => 'old.txt']),
            ];
        },
        'method' => 'putJson',
        'route' => fn (array $ctx): string => "files/{$ctx['file']->id}",
        'payload' => fn (array $ctx): array => ['name' => 'new.txt'],
        'subject_type' => 'file',
        'subject_id' => fn (array $ctx, mixed $response): string => (string) $ctx['file']->id,
        'expected_status' => 200,
    ]],
    'move file' => [[
        'setup' => function (): array {
            $lead = activityLead();

            return [
                'lead' => $lead,
                'file' => activityFile($lead),
                'folder' => activityFolder($lead, ['name' => 'dest']),
            ];
        },
        'method' => 'postJson',
        'route' => fn (array $ctx): string => "files/{$ctx['file']->id}/move",
        'payload' => fn (array $ctx): array => ['destination_folder_id' => $ctx['folder']->id],
        'subject_type' => 'file',
        'subject_id' => fn (array $ctx, mixed $response): string => (string) $ctx['file']->id,
        'assert_successful' => true,
    ]],
    'delete file' => [[
        'setup' => function (): array {
            $lead = activityLead();

            return [
                'lead' => $lead,
                'file' => activityFile($lead),
            ];
        },
        'method' => 'deleteJson',
        'route' => fn (array $ctx): string => "files/{$ctx['file']->id}",
        'payload' => null,
        'subject_type' => 'file',
        'subject_id' => fn (array $ctx, mixed $response): string => (string) $ctx['file']->id,
        'expected_status' => 200,
    ]],
    'create folder' => [[
        'setup' => fn (): array => ['lead' => activityLead()],
        'method' => 'postJson',
        'route' => fn (array $ctx): string => 'files/folders',
        'payload' => fn (array $ctx): array => [
            'fileable_type' => 'lead',
            'fileable_id' => $ctx['lead']->id,
            'name' => 'New Folder',
        ],
        'subject_type' => 'file_folder',
        'subject_id' => fn (array $ctx, mixed $response): string => (string) $response->json('data.id'),
        'expected_status' => 201,
    ]],
    'rename folder' => [[
        'setup' => function (): array {
            $lead = activityLead();

            return [
                'lead' => $lead,
                'folder' => activityFolder($lead, ['name' => 'old']),
            ];
        },
        'method' => 'putJson',
        'route' => fn (array $ctx): string => "files/folders/{$ctx['folder']->id}",
        'payload' => fn (array $ctx): array => ['name' => 'new'],
        'subject_type' => 'file_folder',
        'subject_id' => fn (array $ctx, mixed $response): string => (string) $ctx['folder']->id,
        'expected_status' => 200,
    ]],
    'delete folder' => [[
        'setup' => function (): array {
            $lead = activityLead();

            return [
                'lead' => $lead,
                'folder' => activityFolder($lead),
            ];
        },
        'method' => 'deleteJson',
        'route' => fn (array $ctx): string => "files/folders/{$ctx['folder']->id}",
        'payload' => null,
        'subject_type' => 'file_folder',
        'subject_id' => fn (array $ctx, mixed $response): string => (string) $ctx['folder']->id,
        'expected_status' => 200,
    ]],
]);

it('records activity for file and folder mutations', function (array $scenario) {
    $context = $scenario['setup']();
    $route = $scenario['route']($context);
    $method = (string) $scenario['method'];
    $payloadFactory = $scenario['payload'];
    $payload = $payloadFactory instanceof Closure ? $payloadFactory($context) : null;

    $request = $this->actingAs($this->user, 'tenant-api');
    $response = $payload === null
        ? $request->{$method}($this->tenantApiUrl($route))
        : $request->{$method}($this->tenantApiUrl($route), $payload);

    if (($scenario['assert_successful'] ?? false) === true) {
        $response->assertSuccessful();
    } else {
        $expectedStatus = (int) $scenario['expected_status'];

        if ($expectedStatus === 201) {
            $response->assertCreated();
        } else {
            $response->assertStatus($expectedStatus);
        }
    }

    $subjectType = (string) $scenario['subject_type'];
    $subjectId = (string) $scenario['subject_id']($context, $response);
    $event = latestActivityForSubject($subjectType, $subjectId);

    expect($event)->not->toBeNull();
    $expectedToken = $subjectType === 'file' ? 'file' : 'folder';
    $eventType = (string) ($event?->event_type ?? '');

    expect($eventType)->toMatch('/(^|\\.)'.preg_quote($expectedToken, '/').'(\\.|$)/');
})->with('activityScenarios');

<?php

use App\Enums\FileUploadSessionStatus;
use App\Models\Tenant\Commons\Files\File;
use App\Models\Tenant\Commons\Files\FileUploadSession;
use App\Models\Tenant\CRM\Leads\Lead;
use App\Services\Tenant\Commons\Files\Uploads\MultipartGateway;
use Illuminate\Support\Str;

// ── Helpers ─────────────────────────────────────────────────────────────────

function uploadLead(): Lead
{
    $lead = Lead::factory()->make();
    $lead->save();

    return $lead;
}

function mockMultipartGateway(bool $shouldFailComplete = false): void
{
    $mock = Mockery::mock(MultipartGateway::class);

    $mock->shouldReceive('initiate')
        ->andReturnUsing(fn () => 'mock-upload-id-'.Str::random(8));

    $mock->shouldReceive('signPart')
        ->andReturnUsing(fn ($_disk, $objectKey, $_uploadId, $partNumber, $expires) => [
            'url' => "https://s3.example.com/{$objectKey}?partNumber={$partNumber}",
            'headers' => ['x-amz-request-id' => 'mock'],
            'expires_at' => now()->addSeconds($expires)->toIso8601String(),
        ]);

    $mock->shouldReceive('listUploadedParts')
        ->andReturn([
            ['part_number' => 1, 'etag' => '"etag1"', 'size' => 5242880],
        ]);

    if ($shouldFailComplete) {
        $mock->shouldReceive('complete')->andThrow(new RuntimeException('Multipart completion failed.'));
    } else {
        $mock->shouldReceive('complete')->andReturnNull();
    }

    $mock->shouldReceive('abort')->andReturnNull();

    app()->instance(MultipartGateway::class, $mock);
}

function createUploadSession(Lead $lead, array $attrs = []): FileUploadSession
{
    return FileUploadSession::query()->create(array_merge([
        'fileable_type' => $lead->getMorphClass(),
        'fileable_id' => $lead->getKey(),
        'name' => 'test-file.pdf',
        'original_filename' => 'test-file.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 10485760,
        'disk' => 's3',
        'object_key' => 'files/lead/'.$lead->getKey().'/'.Str::ulid().'-test-file.pdf',
        'provider_upload_id' => 'mock-upload-id-'.Str::random(8),
        'chunk_size_bytes' => 5242880,
        'part_count' => 2,
        'status' => FileUploadSessionStatus::INITIATED,
        'expires_at' => now()->addHours(6),
        'actor_id' => null,
    ], $attrs));
}

// ── Initiate ────────────────────────────────────────────────────────────────

it('initiates an upload session', function () {
    mockMultipartGateway();
    $lead = uploadLead();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('files/uploads'), [
            'idempotency_key' => Str::uuid()->toString(),
            'uploads' => [
                [
                    'fileable_type' => 'lead',
                    'fileable_id' => $lead->id,
                    'name' => 'document.pdf',
                    'original_filename' => 'document.pdf',
                    'mime_type' => 'application/pdf',
                    'size_bytes' => 10485760,
                    'chunk_size_bytes' => 5242880,
                    'part_count' => 2,
                    'client_upload_id' => Str::uuid()->toString(),
                ],
            ],
        ]);

    $response->assertCreated();
    $response->assertJsonStructure([
        'data' => [
            'uploads' => [
                ['id', 'status', 'object_key'],
            ],
        ],
    ]);
});

// ── Sign Parts ──────────────────────────────────────────────────────────────

it('signs upload parts', function () {
    mockMultipartGateway();
    $lead = uploadLead();
    $session = createUploadSession($lead, ['actor_id' => $this->user->getKey()]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("files/uploads/{$session->id}/parts/sign"), [
            'part_numbers' => [1, 2],
        ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            'upload',
            'parts',
        ],
    ]);
});

it('blocks signing parts when upload is completing', function () {
    mockMultipartGateway();
    $lead = uploadLead();
    $session = createUploadSession($lead, [
        'actor_id' => $this->user->getKey(),
        'status' => FileUploadSessionStatus::COMPLETING,
    ]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("files/uploads/{$session->id}/parts/sign"), [
            'part_numbers' => [1],
        ]);

    $response->assertStatus(409);
});

// ── Show ────────────────────────────────────────────────────────────────────

it('shows upload session with uploaded parts', function () {
    mockMultipartGateway();
    $lead = uploadLead();
    $session = createUploadSession($lead, ['actor_id' => $this->user->getKey()]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("files/uploads/{$session->id}"));

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            'upload',
            'uploaded_parts',
        ],
    ]);
});

// ── Abort ────────────────────────────────────────────────────────────────────

it('aborts an upload session', function () {
    mockMultipartGateway();
    $lead = uploadLead();
    $session = createUploadSession($lead, ['actor_id' => $this->user->getKey()]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("files/uploads/{$session->id}"));

    $response->assertStatus(202);
    expect($session->fresh()->status)->toBe(FileUploadSessionStatus::ABORTED);
});

// ── Complete ────────────────────────────────────────────────────────────────

it('completes an upload session and creates file metadata', function () {
    mockMultipartGateway();
    $lead = uploadLead();
    $session = createUploadSession($lead, [
        'actor_id' => $this->user->getKey(),
        'part_count' => 2,
    ]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("files/uploads/{$session->id}/complete"), [
            'idempotency_key' => Str::uuid()->toString(),
            'parts' => [
                ['part_number' => 1, 'etag' => 'etag1'],
                ['part_number' => 2, 'etag' => 'etag2'],
            ],
        ]);

    $response->assertCreated();
    $response->assertHeader('Idempotency-Replayed', 'false');

    $freshSession = $session->fresh();

    expect($freshSession->status)->toBe(FileUploadSessionStatus::COMPLETED);
    expect($freshSession->file_id)->not->toBeNull();

    $file = File::query()->whereKey($freshSession->file_id)->first();
    expect($file)->not->toBeNull();
    expect($file?->storage_path)->toBe($session->object_key);
    $response->assertJsonPath('data.file.storage_path', $session->object_key);
});

it('handles duplicate complete requests idempotently', function () {
    mockMultipartGateway();
    $lead = uploadLead();
    $session = createUploadSession($lead, [
        'actor_id' => $this->user->getKey(),
        'part_count' => 2,
    ]);

    $payload = [
        'idempotency_key' => Str::uuid()->toString(),
        'parts' => [
            ['part_number' => 1, 'etag' => 'etag1'],
            ['part_number' => 2, 'etag' => 'etag2'],
        ],
    ];

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("files/uploads/{$session->id}/complete"), $payload);

    $response->assertCreated();
    $response->assertHeader('Idempotency-Replayed', 'false');

    $firstSession = $session->fresh();
    expect($firstSession->status)->toBe(FileUploadSessionStatus::COMPLETED);
    $firstFileId = (string) $firstSession->file_id;

    $response2 = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("files/uploads/{$session->id}/complete"), $payload);

    $response2->assertCreated();
    $response2->assertHeader('Idempotency-Replayed', 'true');

    $secondSession = $session->fresh();
    expect($secondSession->status)->toBe(FileUploadSessionStatus::COMPLETED);
    expect((string) $secondSession->file_id)->toBe($firstFileId);

    $fileCount = File::query()
        ->where('fileable_type', $lead->getMorphClass())
        ->where('fileable_id', $lead->getKey())
        ->where('storage_path', $session->object_key)
        ->count();

    expect($fileCount)->toBe(1);
});

it('marks upload session as failed when provider completion fails', function () {
    mockMultipartGateway(shouldFailComplete: true);
    $lead = uploadLead();
    $session = createUploadSession($lead, [
        'actor_id' => $this->user->getKey(),
        'part_count' => 2,
    ]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("files/uploads/{$session->id}/complete"), [
            'idempotency_key' => Str::uuid()->toString(),
            'parts' => [
                ['part_number' => 1, 'etag' => 'etag1'],
                ['part_number' => 2, 'etag' => 'etag2'],
            ],
        ]);

    $response->assertServerError();

    $freshSession = $session->fresh();
    expect($freshSession->status)->toBe(FileUploadSessionStatus::FAILED);
    expect($freshSession->failure_code)->toBe('UPLOAD_COMPLETE_FAILED');
    expect((string) $freshSession->failure_reason)->toContain('Multipart completion failed.');
});

it('rejects completion when part numbers are not contiguous from one', function () {
    mockMultipartGateway();
    $lead = uploadLead();
    $session = createUploadSession($lead, [
        'actor_id' => $this->user->getKey(),
        'part_count' => 2,
    ]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("files/uploads/{$session->id}/complete"), [
            'idempotency_key' => Str::uuid()->toString(),
            'parts' => [
                ['part_number' => 2, 'etag' => 'etag2'],
                ['part_number' => 3, 'etag' => 'etag3'],
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.0.source.pointer', 'parts');
});

it('blocks completion when upload is already completing', function () {
    mockMultipartGateway();
    $lead = uploadLead();
    $session = createUploadSession($lead, [
        'actor_id' => $this->user->getKey(),
        'status' => FileUploadSessionStatus::COMPLETING,
        'part_count' => 2,
    ]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("files/uploads/{$session->id}/complete"), [
            'idempotency_key' => Str::uuid()->toString(),
            'parts' => [
                ['part_number' => 1, 'etag' => 'etag1'],
                ['part_number' => 2, 'etag' => 'etag2'],
            ],
        ]);

    $response->assertStatus(409);
});

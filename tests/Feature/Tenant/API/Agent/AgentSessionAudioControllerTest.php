<?php

use App\Models\Tenant\Agent\AgentSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

describe('Agent Session Audio API', function () {
    describe('Authentication', function () {
        it('rejects requests without valid credentials when api key is set', function () {
            config(['runners.api_key' => 'test-secret']);
            $session = AgentSession::factory()->create(['external_session_id' => 'sess_audio_auth']);

            $response = $this->postJson(
                $this->tenantApiUrl('ai/runner/sessions/sess_audio_auth/audio'),
                ['audio' => UploadedFile::fake()->create('recording.wav', 1024)],
            );

            $response->assertUnauthorized();
        });

        it('accepts requests with valid api key', function () {
            config(['runners.api_key' => 'test-secret']);
            Storage::fake('local');
            $session = AgentSession::factory()->create(['external_session_id' => 'sess_audio_ok']);

            $response = $this->withHeaders(['X-Tito-Agent-Key' => 'test-secret'])
                ->postJson(
                    $this->tenantApiUrl('ai/runner/sessions/sess_audio_ok/audio'),
                    ['audio' => UploadedFile::fake()->create('recording.wav', 1024)],
                );

            $response->assertCreated();
        });
    });

    describe('Audio Upload', function () {
        beforeEach(function () {
            config(['runners.api_key' => null]);
            Storage::fake('local');
        });

        it('uploads audio for a session', function () {
            $session = AgentSession::factory()->create(['external_session_id' => 'sess_upload_001']);

            $response = $this->postJson(
                $this->tenantApiUrl('ai/runner/sessions/sess_upload_001/audio'),
                ['audio' => UploadedFile::fake()->create('recording.wav', 2048)],
            );

            $response->assertCreated();
            $response->assertJsonStructure(['data' => ['id', 'name', 'size'], 'message']);
            $response->assertJsonPath('message', 'Audio uploaded.');
        });

        it('returns 404 for non-existent session', function () {
            $response = $this->postJson(
                $this->tenantApiUrl('ai/runner/sessions/sess_nonexistent/audio'),
                ['audio' => UploadedFile::fake()->create('recording.wav', 1024)],
            );

            $response->assertNotFound();
        });

        it('requires audio file', function () {
            $session = AgentSession::factory()->create(['external_session_id' => 'sess_no_file']);

            $response = $this->postJson(
                $this->tenantApiUrl('ai/runner/sessions/sess_no_file/audio'),
                [],
            );

            $response->assertUnprocessable();
        });

        it('rejects files exceeding max size', function () {
            $session = AgentSession::factory()->create(['external_session_id' => 'sess_big_file']);

            $response = $this->postJson(
                $this->tenantApiUrl('ai/runner/sessions/sess_big_file/audio'),
                ['audio' => UploadedFile::fake()->create('huge.wav', 200000)],
            );

            $response->assertUnprocessable();
        });
    });
});

<?php

declare(strict_types=1);

namespace App\Actions\Tenant\Agent\Session;

use App\Models\Tenant\Agent\AgentSession;
use App\Models\Tenant\Agent\AgentSessionAudio;
use Illuminate\Http\UploadedFile;

final class UploadSessionAudio
{
    public function __invoke(AgentSession $session, UploadedFile $file): AgentSessionAudio
    {
        $path = $file->store(
            "agents/{$session->agent_id}/sessions/{$session->id}/audio",
            'local'
        );

        return $session->audio()->create([
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }
}

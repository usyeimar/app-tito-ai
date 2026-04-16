<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Web\Me;

use App\Http\Controllers\Controller;
use App\Services\Central\Auth\Passkey\PasskeyService;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laragear\WebAuthn\Http\Requests\AttestationRequest;
use Laragear\WebAuthn\Http\Requests\AttestedRequest;

class PasskeyController extends Controller
{
    public function __construct(
        private readonly PasskeyService $passkeyService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->passkeyService->listPasskeys($request->user());

        return response()->json($result);
    }

    public function registerOptions(AttestationRequest $request): Responsable
    {
        $this->passkeyService->ensureVerifiedEmail(
            $request->user(),
            'Email verification is required to manage passkeys.'
        );

        return $request
            ->userless()
            ->secureRegistration()
            ->toCreate();
    }

    public function store(AttestedRequest $request): JsonResponse
    {
        $request->validate([
            'alias' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $this->passkeyService->ensureRecentPasswordConfirmation($request->user());

        $request->save($request->only('alias'));

        return response()->json(['message' => 'Passkey registered.']);
    }

    public function destroy(Request $request, string $credential): JsonResponse
    {
        $result = $this->passkeyService->revokePasskey($request->user(), $credential);

        return response()->json($result);
    }
}

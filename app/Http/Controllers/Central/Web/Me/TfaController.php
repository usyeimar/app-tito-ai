<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Web\Me;

use App\Http\Controllers\Controller;
use App\Services\Central\Auth\Tfa\TfaService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

class TfaController extends Controller
{
    public function __construct(
        private readonly TfaService $tfaService,
    ) {}

    public function enable(Request $request): JsonResponse
    {
        $result = $this->tfaService->enableTfa($request->user());

        return response()->json($result);
    }

    public function qrCode(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->two_factor_secret) {
            return response()->json(['message' => 'Two-factor authentication is not initialized.'], 422);
        }

        $renderer = new ImageRenderer(
            new RendererStyle(192),
            new SvgImageBackEnd,
        );

        $writer = new Writer($renderer);

        $otpauthUrl = (new Google2FA)->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $user->two_factor_secret,
        );

        return response()->json([
            'svg' => $writer->writeString($otpauthUrl),
        ]);
    }

    public function secretKey(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->two_factor_secret) {
            return response()->json(['message' => 'Two-factor authentication is not initialized.'], 422);
        }

        return response()->json([
            'secretKey' => $user->two_factor_secret,
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $result = $this->tfaService->confirmTfa($request->user(), $request->input('code'));

        return response()->json($result);
    }

    public function recoveryCodes(Request $request): JsonResponse
    {
        $result = $this->tfaService->getRecoveryCodes($request->user());
        $codes = $result['recovery_codes'] ?? [];

        $formatted = [];
        foreach ($codes as $entry) {
            if (is_array($entry) && array_key_exists('code', $entry)) {
                $formatted[] = $entry['code'];
            } elseif (is_string($entry)) {
                $formatted[] = $entry;
            }
        }

        return response()->json($formatted);
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $result = $this->tfaService->regenerateRecoveryCodes($request->user());
        $codes = $result['recovery_codes'] ?? [];

        $formatted = [];
        foreach ($codes as $entry) {
            if (is_array($entry) && array_key_exists('code', $entry)) {
                $formatted[] = $entry['code'];
            } elseif (is_string($entry)) {
                $formatted[] = $entry;
            }
        }

        return response()->json($formatted);
    }

    public function disable(Request $request): JsonResponse
    {
        $result = $this->tfaService->disableTfa($request->user());

        return response()->json($result);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Web\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Web\Me\PasswordUpdateRequest;
use App\Services\Central\Auth\Password\PasswordConfirmationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Inertia\Response;
use Laravel\Fortify\Features;

class SecurityController extends Controller
{
    public function __construct(
        private readonly PasswordConfirmationService $passwordConfirmationService,
    ) {}

    public function show(Request $request): Response
    {
        $user = $request->user();

        $props = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            'status' => $request->session()->get('status'),
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $props['twoFactorEnabled'] = (bool) $user->two_factor_enabled;
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        return inertia('me/security', $props);
    }

    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        return back()->with('status', 'password-updated');
    }

    public function confirmationStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'confirmed' => Cache::has("auth:confirmed:{$user->id}"),
        ]);
    }

    public function confirmPassword(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'errors' => [
                    'password' => [__('The provided password is incorrect.')],
                ],
            ], 422);
        }

        $this->passwordConfirmationService->markPasswordConfirmed($user);

        return response()->json(['confirmed' => true], 201);
    }
}

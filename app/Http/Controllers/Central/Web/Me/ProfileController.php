<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Web\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Web\Me\ProfileUpdateRequest;
use App\Services\Central\Auth\Profile\ProfileService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService,
    ) {}

    public function show(Request $request): Response
    {
        $user = $request->user()->load('profilePicture');

        $avatarUrl = $user->profilePicture?->path
            ? route('me.profile-picture.show').'?v='.$user->profilePicture->updated_at?->timestamp
            : null;

        return inertia('me/profile', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'avatar_url' => $avatarUrl,
            ],
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return back();
    }

    public function updateProfilePicture(Request $request): RedirectResponse
    {
        $request->validate([
            'profile_picture' => ['required', 'image', 'max:2048'],
        ]);

        $this->profileService->updateProfilePicture(
            $request->user(),
            $request->file('profile_picture'),
        );

        return back();
    }

    public function removeProfilePicture(Request $request): RedirectResponse
    {
        $this->profileService->removeProfilePicture($request->user());

        return back();
    }

    public function showProfilePicture(Request $request): SymfonyResponse
    {
        $user = $request->user()->load('profilePicture');
        $picture = $user->profilePicture;

        if (! $picture?->path) {
            abort(404);
        }

        $disk = Storage::disk(config('filesystems.default'));

        if (! $disk->exists($picture->path)) {
            abort(404);
        }

        return $disk->response($picture->path, null, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}

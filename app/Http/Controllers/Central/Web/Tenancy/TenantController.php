<?php

namespace App\Http\Controllers\Central\Web\Tenancy;

use App\Exceptions\TenantImpersonationUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\API\Tenancy\StoreTenantRequest;
use App\Http\Resources\Central\API\Tenancy\TenantResource;
use App\Models\Central\Tenancy\Tenant;
use App\Models\Central\Tenancy\TenantInvitation;
use App\Services\Central\Auth\Impersonation\TenantImpersonationService;
use App\Services\Central\Tenancy\TenantService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function __construct(
        private readonly TenantService $tenantService,
        private readonly TenantImpersonationService $tenantImpersonationService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $tenants = $this->tenantService->listForUser($user);

        $invitations = TenantInvitation::query()
            ->where('email', $user->email)
            ->pending()
            ->with('tenant')
            ->get();

        return Inertia::render('workspaces/index', [
            'workspaces' => collect($tenants->items())->map(fn ($tenant) => TenantResource::make($tenant)->resolve()),
            'appUrl' => config('app.url'),
            'invitations' => $invitations->map(fn (TenantInvitation $invitation) => [
                'id' => $invitation->id,
                'tenant' => [
                    'id' => $invitation->tenant->id,
                    'name' => $invitation->tenant->name,
                    'slug' => $invitation->tenant->slug,
                ],
                'expires_at' => $invitation->expires_at,
            ]),
        ]);
    }

    public function store(StoreTenantRequest $request)
    {
        $this->tenantService->createForUser(
            $request->user(),
            $request->validated(),
        );

        return redirect()->route('workspaces');
    }

    public function enter(Request $request, Tenant $tenant)
    {
        $redirectUrl = route('tenant.dashboard', ['tenant' => $tenant->slug]);

        try {
            $result = $this->tenantImpersonationService->createImpersonationUrl(
                $request->user(),
                $tenant->slug,
                $redirectUrl,
            );

            Log::info('Tenant impersonation URL created.', [
                'user_id' => $request->user()?->id,
                'tenant' => $tenant->slug,
                'redirect_url' => $redirectUrl,
                'ip' => $request->ip(),
            ]);
        } catch (AuthorizationException $e) {
            return redirect()->route('workspaces')
                ->withErrors(['message' => 'You do not have access to this workspace.']);
        } catch (TenantImpersonationUnavailableException $e) {
            return redirect()->route('workspaces')
                ->withErrors(['message' => $e->getMessage()]);
        }

        Log::info('Tenant impersonation issued.', [
            'user_id' => $request->user()?->id,
            'tenant' => $tenant->slug,
            'redirect_url' => $redirectUrl,
            'ip' => $request->ip(),
        ]);

        return redirect()->route('tenant.impersonate', [
            'tenant' => $tenant->slug,
            'token' => $result['token'],
        ]);
    }
}

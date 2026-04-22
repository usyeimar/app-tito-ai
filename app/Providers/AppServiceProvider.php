<?php

namespace App\Providers;

use App\Models\Central\Auth\Authentication\CentralUser;
use App\Models\Central\System\SystemProfilePicture as CentralSystemProfilePicture;
use App\Models\Tenant\Agent\AgentSession;
use App\Models\Tenant\Auth\Authentication\User as TenantUser;
use App\Models\Tenant\System\SystemProfilePicture as TenantSystemProfilePicture;
use App\Models\User;
use FilesystemIterator;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\Provider;
use Stancl\Tenancy\Features\UserImpersonation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDatabase();
        $this->configureUrlGeneration();
        $this->configurePasswordValidation();
        $this->configureRateLimiting();
        $this->configureAuthNotifications();
        $this->configureAuthorization();
        $this->configureTenancy();
        $this->configureSocialite();
    }

    /**
     * Configure the database settings and migrations.
     */
    private function configureDatabase(): void
    {
        $this->loadMigrationsFrom($this->migrationPaths(base_path('database/migrations/central')));
    }

    private function configureUrlGeneration(): void
    {
        $appUrl = trim((string) config('app.url', ''));

        if ($appUrl === '') {
            return;
        }

        URL::forceRootUrl($appUrl);

        $scheme = parse_url($appUrl, PHP_URL_SCHEME);

        if (is_string($scheme) && $scheme !== '') {
            URL::forceScheme($scheme);
        }
    }

    /**
     * Configure the password validation rules.
     */
    private function configurePasswordValidation(): void
    {
        Password::defaults(function () {
            return Password::min(8)->mixedCase()->letters()->numbers()->symbols()->uncompromised();
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth.login', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email', ''));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('auth.register', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('auth.tfa_verify', function (Request $request): Limit {
            $token = (string) $request->input('tfa_token', '');

            return Limit::perMinute(10)->by($token.'|'.$request->ip());
        });

        RateLimiter::for('auth.forgot_password', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email', ''));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('auth.email_verification', function (Request $request): Limit {
            // Use authenticated user's email if available, fallback to input email
            $email = Str::lower((string) ($request->user()?->email ?? $request->input('email', 'guest')));

            return Limit::perMinute(3)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('auth.social_login', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->path().'|'.$request->ip());
        });

        RateLimiter::for('auth.passkeys', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('auth.refresh', function (Request $request): Limit {
            $token = (string) $request->input('refresh_token', '');
            $key = $token !== '' ? $token.'|'.$request->ip() : $request->ip();

            return Limit::perMinute(10)->by($key);
        });

        RateLimiter::for('tenant.users.reset_password', function (Request $request): Limit {
            $userKey = $request->user()?->getKey() ?? 'guest';

            return Limit::perMinute(5)->by($userKey.'|'.$request->ip());
        });

        RateLimiter::for('tenant.invitations.resend', function (Request $request): Limit {
            $invitation = (string) $request->route('invitation', '');
            $userKey = $request->user()?->getKey() ?? 'guest';

            return Limit::perMinute(5)->by($userKey.'|'.$invitation.'|'.$request->ip());
        });

        RateLimiter::for('tenant.files.upload.initiate', function (Request $request): Limit {
            $limit = max(1, (int) config('files_module.rate_limits.upload_initiate_per_minute', 60));
            $userKey = $request->user()?->getKey() ?? 'guest';

            return Limit::perMinute($limit)->by($userKey.'|'.$request->ip());
        });

        RateLimiter::for('tenant.files.upload.sign_parts', function (Request $request): Limit {
            $limit = max(1, (int) config('files_module.rate_limits.upload_sign_parts_per_minute', 300));
            $userKey = $request->user()?->getKey() ?? 'guest';

            return Limit::perMinute($limit)->by($userKey.'|'.$request->ip());
        });

        RateLimiter::for('tenant.files.upload.complete', function (Request $request): Limit {
            $limit = max(1, (int) config('files_module.rate_limits.upload_complete_per_minute', 120));
            $userKey = $request->user()?->getKey() ?? 'guest';

            return Limit::perMinute($limit)->by($userKey.'|'.$request->ip());
        });

        RateLimiter::for('tenant.files.upload.abort', function (Request $request): Limit {
            $limit = max(1, (int) config('files_module.rate_limits.upload_abort_per_minute', 120));
            $userKey = $request->user()?->getKey() ?? 'guest';

            return Limit::perMinute($limit)->by($userKey.'|'.$request->ip());
        });

        RateLimiter::for('tenant.files.upload.resume', function (Request $request): Limit {
            $limit = max(1, (int) config('files_module.rate_limits.upload_resume_per_minute', 300));
            $userKey = $request->user()?->getKey() ?? 'guest';

            return Limit::perMinute($limit)->by($userKey.'|'.$request->ip());
        });

        RateLimiter::for('tenant.files.download', function (Request $request): Limit {
            $limit = max(1, (int) config('files_module.rate_limits.download_per_minute', 120));
            $userKey = $request->user()?->getKey() ?? 'guest';
            $fileId = (string) $request->route('file', '');

            return Limit::perMinute($limit)->by($userKey.'|'.$fileId.'|'.$request->ip());
        });

    }

    /**
     * Configure authorization policies and morph maps.
     */
    private function configureAuthorization(): void
    {
        Gate::before(function ($user, string $ability) {
            if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
                if (method_exists($user, 'hasVerifiedEmail') && ! $user->hasVerifiedEmail()) {
                    return null;
                }

                return true;
            }

            return null;
        });

        Relation::enforceMorphMap([
            'user' => User::class,
            'central_user' => CentralUser::class,
            'tenant_user' => TenantUser::class,
            'central_system_profile_picture' => CentralSystemProfilePicture::class,
            'tenant_system_profile_picture' => TenantSystemProfilePicture::class,
            'tenant_agent_session' => AgentSession::class,
        ]);
    }

    /**
     * Configure authentication notifications (Verify Email & Reset Password).
     */
    private function configureAuthNotifications(): void
    {

        VerifyEmail::toMailUsing(function (object $notifiable, string $url): MailMessage {
            return (new MailMessage)
                ->subject('Verify Your Email Address - Tito')
                ->view('emails.verify-email', [
                    'url' => $url,
                    'notifiable' => $notifiable,
                    'expire' => config('auth.verification.expire', 60),
                ]);
        });

        // Reset Password Configuration
        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');

            $email = method_exists($notifiable, 'getEmailForPasswordReset')
                ? (string) $notifiable->getEmailForPasswordReset()
                : '';

            $query = http_build_query([
                'token' => $token,
                'email' => $email,
            ]);

            return Str::finish($frontendUrl, '/')."reset-password?{$query}";
        });

        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
            $email = method_exists($notifiable, 'getEmailForPasswordReset')
                ? (string) $notifiable->getEmailForPasswordReset()
                : '';

            $query = http_build_query([
                'token' => $token,
                'email' => $email,
            ]);

            $url = Str::finish($frontendUrl, '/')."reset-password?{$query}";

            return (new MailMessage)
                ->subject('Reset Your Password - Tito')
                ->view('emails.forgot-password', [
                    'url' => $url,
                    'notifiable' => $notifiable,
                    'expire' => config('auth.passwords.users.expire'),
                ]);
        });
    }

    /**
     * Configure Tenancy specific features.
     */
    private function configureTenancy(): void
    {
        UserImpersonation::$ttl = (int) config('tenancy.impersonation_ttl', 60);

        // Configure Passport cookie name for central context
        Passport::cookie(config('passport_tokens.access_cookie.central_name', 'central_access_token'));
    }

    /**
     * Configure Socialite providers.
     */
    private function configureSocialite(): void
    {
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('microsoft', Provider::class);
        });
    }

    /**
     * Get recursive migration paths.
     *
     * @return array<int, string>
     */
    private function migrationPaths(string $root): array
    {
        if (! is_dir($root)) {
            return [];
        }

        $paths = [$root];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                $paths[] = $fileInfo->getPathname();
            }
        }

        return $paths;
    }
}

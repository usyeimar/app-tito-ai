# Documentación del Sistema de Autenticación

## 1. Arquitectura General

Tu aplicación implementa un **sistema de autenticación multi-tenant** con Laravel Passport, usando una arquitectura de dos niveles:

```
┌─────────────────────────────────────────────────────────────────┐
│                      BASE DE DATOS CENTRAL                      │
│  ┌─────────────────┐    ┌─────────────────┐                    │
│  │   CentralUser   │    │ OAuth Clients   │                    │
│  │ (WebAuthn, 2FA) │    │ (central/tenant)│                    │
│  └────────┬────────┘    └─────────────────┘                    │
└───────────┼─────────────────────────────────────────────────────┘
            │ sync (ResourceSyncing)
            ▼
┌─────────────────────────────────────────────────────────────────┐
│                    BASE DE DATOS POR TENANT                     │
│  ┌─────────────────┐    ┌─────────────────┐                    │
│  │   Tenant\User   │    │ Tenant OAuth    │                    │
│  └─────────────────┘    └─────────────────┘                    │
└─────────────────────────────────────────────────────────────────┘
```

---

## 2. Modelos de Usuario

### 2.1 CentralUser (`app/Models/Central/Auth/Authentication/CentralUser.php`)

Es el usuario raíz en la base de datos central. Implements multiple interfaces:

```php
class CentralUser extends Authenticatable implements
    MustVerifyEmail,           // Verificación de email
    OAuthenticatable,         // Passport integration
    SyncMaster,               // ResourceSyncing to tenants
    WebAuthnAuthenticatable   // Passwordless/WebAuthn
```

**Traits usados:**

- `HasApiTokens` - Token management
- `HasRoles` (Spatie) - Roles y permisos
- `WebAuthnAuthentication` - WebAuthn/FIDO2
- `ResourceSyncing` - Sincronización con tenants
- `HasUlids` - ULID IDs

**Campos importantes:**

- `global_id` - Identificador único global
- `two_factor_enabled`, `two_factor_secret`, `two_factor_recovery_codes`
- `email_verification_sent_at` - Control de emails

### 2.2 Tenant\User (`app/Models/Tenant/Auth/Authentication/User.php`)

Usuario sincronizado en cada base de datos de tenant:

```php
class User extends Authenticatable implements Syncable
```

- Sincroniza: `global_id`, `name`, `email`, `email_verified_at`, `password`
- No tiene WebAuthn ni 2FA (usa la autenticación central)

---

## 3. Guards y Providers (`config/auth.php`)

### Guards configurados:

| Guard         | Driver   | Provider        | Uso              |
| ------------- | -------- | --------------- | ---------------- |
| `web`         | session  | `central_users` | UI web central   |
| `tenant`      | session  | `tenant_users`  | UI por tenant    |
| `central-api` | passport | `central_users` | API REST central |
| `tenant-api`  | passport | `tenant_users`  | API REST tenant  |

### Providers:

```php
'providers' => [
    'central_users' => [
        'driver' => 'eloquent-webauthn',  // ⚠️ Custom driver
        'model' => CentralUser::class,
        'password_fallback' => true,      // ⚠️ Permite password además de WebAuthn
    ],
    'tenant_users' => [
        'driver' => 'eloquent',
        'model' => User::class,
    ],
]
```

**⚠️ MODIFICACIÓN IMPORTANTE:** El driver `eloquent-webauthn` es personalizado, no el estándar de Passport. Esto permite autenticación WebAuthn con password fallback.

---

## 4. Laravel Passport - Configuración

### 4.1 Configuración básica (`config/passport.php`)

```php
'guard' => 'web',
'private_key' => env('PASSPORT_PRIVATE_KEY'),
'public_key' => env('PASSPORT_PUBLIC_KEY'),
'connection' => env('PASSPORT_CONNECTION'),
```

### 4.2 Clientes OAuth (`config/passport_clients.php`)

Tu app usa **clientes OAuth predefinidos** (no dinámicos):

```php
'central' => [
    'client_id' => env('PASSPORT_CENTRAL_CLIENT_ID'),
    'client_secret' => env('PASSPORT_CENTRAL_CLIENT_SECRET'),
],
'tenant' => [
    'client_id' => env('PASSPORT_TENANT_CLIENT_ID'),
    'client_secret' => env('PASSPORT_TENANT_CLIENT_SECRET'),
],
```

### 4.3 TTL de tokens (`config/passport_tokens.php`)

```php
'access_ttl_minutes' => 60,      // 1 hora
'refresh_ttl_days' => 30,        // 30 días
```

### 4.4 Cookies (`config/passport_tokens.php`)

Tokens almacenados en cookiesHttpOnly para SPA:

- `central_access_token` - Path `/api`
- `central_refresh_token` - Path `/api/auth/refresh`
- Cookies configurables con domain, secure, sameSite

---

## 5. Flujos de Autenticación

### 5.1 Login tradicional (Password Grant)

```
POST /api/auth/login
{
  "email": "user@example.com",
  "password": "password",
  "device_name": "My Device"
}
```

**Proceso:**

1. `AuthService::login()` verifica credenciales con `Hash::check()`
2. Si 2FA habilitado → retorna challenge
3. Llama a `Authenticator::authenticate()`
4. `PassportTokenService::issueCentralTokensWithPassword()` → OAuth token

**OAuth flow interno:**

```php
// PassportTokenService.php:22
$this->issueToken([
    'grant_type' => 'password',
    'client_id' => $this->centralClientId(),
    'client_secret' => $this->centralClientSecret(),
    'username' => $email,
    'password' => $password,
]);
```

### 5.2 WebAuthn (Passwordless)

```
POST /api/auth/passkeys/login/options  → Obtiene challenge
POST /api/auth/passkeys/login           → Autentica con passkey
```

**Proceso:**

1. `PasskeyLoginController::options()` - Genera challenge WebAuthn
2. `PasskeyLoginController::login()` - Valida credential
3. `Authenticator::authenticate()` - Genera tokens

**⚠️ MODIFICACIÓN:** En lugar de usar password, genera un token temporal en cache:

```php
// CentralUser.php:191
$loginToken = $user->createPassportLoginToken(); // Token en cache 60s
// Luego usa ese token en password grant
```

### 5.3 Refresh Token

```
POST /api/auth/refresh
Cookie: central_refresh_token=...
```

El servidor valida el refresh token y emite nuevos access tokens.

### 5.4 Impersonation (Custom Grant)

**⚠️ MODIFICACIÓN PERSONALIZADA - No es estándar OAuth2**

Permite a un admin central impersonar usuarios en un tenant:

```php
// Custom grant: ImpersonationTokenGrant
'grant_type' => 'impersonation_token',
'impersonation_token' => '...',
```

**Implementación:** `app/Support/Passport/ImpersonationTokenGrant.php`

- Valida que el token no haya expirado (configurable via `tenancy.impersonation_ttl`)
- Verifica contexto del tenant
- Vincula el `impersonator_central_user_id` al token generado

---

## 6. Modificaciones Personalizadas a Passport

### 6.1 Custom OAuth Driver: `eloquent-webauthn`

En lugar del driver `eloquent` estándar, usas `eloquent-webauthn` que:

- Soporta autenticación WebAuthn/FIDO2
- Mantiene password como fallback (`password_fallback => true`)
- Requiere que el modelo implemente `WebAuthnAuthenticatable`

### 6.2 Custom Grant: ImpersonationTokenGrant

```php
// app/Support/Passport/ImpersonationTokenGrant.php
class ImpersonationTokenGrant extends AbstractGrant
{
    public function getIdentifier(): string
    {
        return 'impersonation_token';
    }
}
```

Registrado en `AuthServiceProvider`:

```php
$grant = new ImpersonationTokenGrant(...);
$server->enableGrantType($grant, $accessTokenTTL);
```

### 6.3 Password Validation con Token en Cache

```php
// CentralUser.php:177
public function validateForPassportPasswordGrant(string $password): bool
{
    // 1. Verifica password hash normal
    if (Hash::check($password, (string) $this->password)) {
        return true;
    }

    // 2. Verifica token temporal en cache (para WebAuthn flow)
    $token = Cache::pull($this->passportLoginTokenCacheKey());
    return $token && Hash::check($password, $token);
}
```

### 6.4 Personal Access Tokens con Scope personalizado

El modelo implementa `OAuthenticatable` para permitir uso con Passport:

```php
public function findForPassport(string $username): ?self
{
    return static::query()
        ->where('email', Str::lower($username))
        ->first();
}

public function getProviderName(): string
{
    return 'central_users'; // ⚠️ Necesario para custom provider
}
```

---

## 7. Servicios Principales

### 7.1 PassportTokenService

`app/Services/Central/Auth/Token/PassportTokenService.php`

Maneja emisión de tokens:

| Método                                 | Uso                            |
| -------------------------------------- | ------------------------------ |
| `issueCentralTokensWithPassword()`     | Password grant estándar        |
| `issueCentralTokensForUser()`          | WebAuthn flow (token en cache) |
| `refreshCentralToken()`                | Refresh token grant            |
| `issueTenantTokensFromImpersonation()` | Custom impersonation grant     |
| `listTokens()`                         | Listar tokens activos          |
| `revokeTokens()`                       | Revocar tokens                 |

### 7.2 TokenCookieService

`app/Services/Central/Auth/Token/TokenCookieService.php`

Maneja cookies de tokens para SPA:

- `shouldUseCookies()` - Decide si usar cookies o bearer token
- `centralAccessCookie()` / `tenantAccessCookie()`
- `centralRefreshCookie()` / `tenantRefreshCookie()`
- `forget*()` - Limpiar cookies en logout

### 7.3 AuthService

`app/Services/Central/Auth/Authentication/AuthService.php`

| Método           | Función                        |
| ---------------- | ------------------------------ |
| `login()`        | Login con password, maneja 2FA |
| `register()`     | Registro de nuevo usuario      |
| `authenticate()` | Emite tokens Passport          |
| `logout()`       | Revoca token actual            |

### 7.4 Authenticator

`app/Services/Central/Auth/Authentication/Authenticator.php`

Wraper que llama a `PassportTokenService` para emitir tokens.

---

## 8. Rutas de Autenticación

### 8.1 Central API (`routes/central/api.php`)

```
POST   /api/auth/register              → Registro
POST   /api/auth/login                  → Login password
POST   /api/auth/logout                 → Logout
GET    /api/auth/me                     → Usuario actual

POST   /api/auth/passkeys/login/options  → WebAuthn options
POST   /api/auth/passkeys/login         → WebAuthn login
POST   /api/auth/passkeys/register/options
POST   /api/auth/passkeys/register

POST   /api/auth/tfa/verify             → Verificar 2FA
POST   /api/auth/tfa/challenge          → Iniciar challenge
POST   /api/auth/tfa/enable             → Habilitar 2FA
POST   /api/auth/tfa/disable            → Deshabilitar 2FA

POST   /api/auth/refresh                → Refresh token
POST   /api/auth/impersonate            → Impersonar tenant

POST   /api/auth/google                 → Social login Google
POST   /api/auth/microsoft              → Social login Microsoft
```

---

## 9. Base de Datos

### 9.1 Tablas OAuth (Central)

```sql
oauth_clients       -- Clients OAuth (central/tenant predefinidos)
oauth_auth_codes    -- Authorization codes
oauth_access_tokens -- Access tokens generados
oauth_refresh_tokens-- Refresh tokens
```

### 9.2 Tablas de Usuario

```sql
-- Central
users (CentralUser)
  - global_id (ULID)
  - email, name, password
  - two_factor_enabled, two_factor_secret
  - email_verified_at, email_verification_sent_at

-- Tenant
users (Tenant\User)
  - global_id (vinculado al central)
  - email, name, password
  - is_active
```

---

## 10. Integración con Tenancy

### 10.1 ResourceSyncing

CentralUser funciona como `SyncMaster` y sincroniza a Tenant\User:

```php
public function getTenantModelName(): string
{
    return User::class;
}

public function getSyncedAttributeNames(): array
{
    return ['global_id', 'name', 'email', 'email_verified_at', 'password'];
}
```

### 10.2 Impersonation

El admin central puede impersonar usuarios en tenants:

1. Genera `ImpersonationToken` en tabla central
2. Usa custom grant `impersonation_token` para obtener tokens del tenant
3. El token generado guarda `impersonator_central_user_id`

---

## 11. Flujo Completo de Login

```
┌──────────────────────────────────────────────────────────────────┐
│                        LOGIN FLOW                                │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. POST /api/auth/login                                        │
│     └─> AuthenticationController::login()                      │
│        ├─> AuthService::login()                                 │
│        │   ├─> Hash::check(password, user->password)            │
│        │   ├─> ¿2FA enabled? → TfaService::startTfaChallenge() │
│        │   └─> authenticate() → Authenticator                  │
│        │       └─> PassportTokenService::issueCentralTokens() │
│        │           └─> OAuth Password Grant → access_token      │
│        │                                                        │
│        └─> AuthResource → JSON response                        │
│                                                                  │
│  2. Response típico:                                           │
│     {                                                           │
│       "kind": "auth",                                           │
│       "user": {...},                                            │
│       "access_token": "eyJ...",                                 │
│       "refresh_token": "def...",                                │
│       "expires_in": 3600,                                      │
│       "token_type": "Bearer",                                   │
│       "tenants": [...]                                          │
│     }                                                           │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

---

## 12. Resumen: Customizaciones Passport

| Customización                      | Archivo                       | Descripción                           |
| ---------------------------------- | ----------------------------- | ------------------------------------- |
| Driver `eloquent-webauthn`         | `config/auth.php`             | Soporta WebAuthn + password fallback  |
| Password validation con cache      | `CentralUser.php:177`         | Acepta token temporal de cache        |
| Custom grant `impersonation_token` | `ImpersonationTokenGrant.php` | Impersonación de usuarios tenant      |
| Provider name override             | `CentralUser.php:165`         | Necesario para custom driver          |
| Predefined OAuth clients           | `config/passport_clients.php` | No usa clientes dinámicos             |
| Token cookies para SPA             | `TokenCookieService.php`      | Cookies HttpOnly en vez de bearer     |
| Impersonator tracking              | `oauth_access_tokens`         | Guarda `impersonator_central_user_id` |

---

## 13. Dependencias Externas

- **laragear/webauthn** - Autenticación WebAuthn/FIDO2
- **laravel/passport** - OAuth2 server
- **laravel/socialite** - Social login (Google, Microsoft)
- **stancl/tenancy** - Multi-tenancy
- **spatie/laravel-permission** - Roles y permisos

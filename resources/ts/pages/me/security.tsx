import { Form, Head, router } from '@inertiajs/react';
import { Fingerprint, ShieldCheck, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import ConfirmPasswordModal from '@/components/confirm-password-modal';
import InputError from '@/components/input-error';
import MeTwoFactorRecoveryCodes from '@/components/me-two-factor-recovery-codes';
import MeTwoFactorSetupModal from '@/components/me-two-factor-setup-modal';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useMeTwoFactorAuth } from '@/hooks/use-me-two-factor-auth';
import { MeLayout } from './me-layout';
import type { User } from '@/types';

interface Props {
    user: User;
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
    status?: string;
}

export default function Security({
    user,
    canManageTwoFactor = false,
    requiresConfirmation = false,
    twoFactorEnabled = false,
    status,
}: Props) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const {
        qrCodeSvg,
        hasSetupData,
        manualSetupKey,
        clearSetupData,
        fetchSetupData,
        recoveryCodesList,
        fetchRecoveryCodes,
        errors: tfaErrors
    } = useMeTwoFactorAuth();
    const [showSetupModal, setShowSetupModal] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);
    const [pendingAction, setPendingAction] = useState<
        'enable' | 'disable' | null
    >(null);

    const checkPasswordConfirmation = useCallback(
        async (action: 'enable' | 'disable') => {
            try {
                const response = await fetch('/me/confirmed-password-status', {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'include'
                });

                const data = await response.json();

                if (data.confirmed) {
                    executeTfaAction(action);
                } else {
                    setPendingAction(action);
                    setShowConfirmPassword(true);
                }
            } catch {
                setPendingAction(action);
                setShowConfirmPassword(true);
            }
        },
        []
    );

    const executeTfaAction = useCallback(
        async (action: 'enable' | 'disable') => {
            const csrfToken =
                document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content') || '';

            const url =
                action === 'enable' ? '/me/tfa/enable' : '/me/tfa/disable';

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'include'
                });

                if (response.ok) {
                    if (action === 'enable') {
                        setShowSetupModal(true);
                    } else {
                        router.reload();
                    }
                } else if (response.status === 422) {
                    const data = await response.json().catch(() => null);
                    const needsConfirmation =
                        data?.errors?.password?.[0]
                            ?.toLowerCase()
                            .includes('confirm') ||
                        data?.message?.toLowerCase().includes('confirm');

                    if (needsConfirmation) {
                        setPendingAction(action);
                        setShowConfirmPassword(true);
                    }
                }
            } catch {
                // network error
            }
        },
        []
    );

    const handlePasswordConfirmed = useCallback(() => {
        setShowConfirmPassword(false);
        if (pendingAction) {
            executeTfaAction(pendingAction);
            setPendingAction(null);
        }
    }, [pendingAction, executeTfaAction]);

    return (
        <>
            <Head title="Security" />
            <MeLayout user={user} activeTab="security">
                <div className="space-y-4">
                    {/* Password Card */}
                    <div className="rounded-xl border border-border bg-card">
                        <Form
                            action="/me/password"
                            method="put"
                            options={{ preserveScroll: true }}
                            resetOnError={[
                                'password',
                                'password_confirmation',
                                'current_password'
                            ]}
                            resetOnSuccess
                            onError={(errors) => {
                                if (errors.password) {
                                    passwordInput.current?.focus();
                                }

                                if (errors.current_password) {
                                    currentPasswordInput.current?.focus();
                                }
                            }}
                        >
                            {({ errors, processing, recentlySuccessful }) => (
                                <>
                                    <div className="p-6">
                                        <h2 className="text-base font-semibold">
                                            Password
                                        </h2>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Change your password to keep your
                                            account secure.
                                        </p>

                                        <div className="mt-6 space-y-4">
                                            <div className="space-y-2">
                                                <Label htmlFor="current_password">
                                                    Current password
                                                </Label>
                                                <PasswordInput
                                                    id="current_password"
                                                    ref={currentPasswordInput}
                                                    name="current_password"
                                                    autoComplete="current-password"
                                                    placeholder="Current password"
                                                />
                                                <InputError
                                                    message={
                                                        errors.current_password
                                                    }
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="password">
                                                    New password
                                                </Label>
                                                <PasswordInput
                                                    id="password"
                                                    ref={passwordInput}
                                                    name="password"
                                                    autoComplete="new-password"
                                                    placeholder="New password"
                                                />
                                                <InputError
                                                    message={errors.password}
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="password_confirmation">
                                                    Confirm new password
                                                </Label>
                                                <PasswordInput
                                                    id="password_confirmation"
                                                    name="password_confirmation"
                                                    autoComplete="new-password"
                                                    placeholder="Confirm password"
                                                />
                                                <InputError
                                                    message={
                                                        errors.password_confirmation
                                                    }
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        className="flex items-center justify-end gap-3 border-t border-border bg-muted/30 px-6 py-4">
                                        {(recentlySuccessful ||
                                            status ===
                                                'password-updated') && (
                                            <p className="text-sm text-muted-foreground">
                                                Password updated
                                            </p>
                                        )}
                                        <Button
                                            type="submit"
                                            size="sm"
                                            disabled={processing}
                                        >
                                            Update password
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </div>

                    {/* Two-factor authentication Card */}
                    {canManageTwoFactor && (
                        <div className="rounded-xl border border-border bg-card">
                            <div className="p-6">
                                <h2 className="text-base font-semibold">
                                    Two-factor authentication
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Add a second verification step to protect
                                    your account.
                                </p>

                                {twoFactorEnabled ? (
                                    <div className="mt-4 space-y-4">
                                        <p className="text-sm text-muted-foreground">
                                            You will be prompted for a secure,
                                            random pin during login, which you
                                            can retrieve from the
                                            TOTP-supported application on your
                                            phone.
                                        </p>

                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            onClick={() =>
                                                checkPasswordConfirmation(
                                                    'disable'
                                                )
                                            }
                                        >
                                            Disable 2FA
                                        </Button>

                                        <MeTwoFactorRecoveryCodes
                                            recoveryCodesList={
                                                recoveryCodesList
                                            }
                                            fetchRecoveryCodes={
                                                fetchRecoveryCodes
                                            }
                                            errors={tfaErrors}
                                        />
                                    </div>
                                ) : (
                                    <div className="mt-4">
                                        <p className="text-sm text-muted-foreground">
                                            When enabled, you will be asked
                                            for a code from your authenticator
                                            app each time you sign in.
                                        </p>
                                    </div>
                                )}
                            </div>

                            {!twoFactorEnabled && (
                                <div className="flex items-center justify-end gap-3 border-t border-border bg-muted/30 px-6 py-4">
                                    {hasSetupData ? (
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                setShowSetupModal(true)
                                            }
                                        >
                                            <ShieldCheck />
                                            Continue setup
                                        </Button>
                                    ) : (
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                checkPasswordConfirmation(
                                                    'enable',
                                                )
                                            }
                                        >
                                            Enable two-factor
                                        </Button>
                                    )}
                                </div>
                            )}
                        </div>
                    )}

                    {/* Passkeys Card */}
                    <PasskeysCard />

                    <MeTwoFactorSetupModal
                        isOpen={showSetupModal}
                        onClose={() => {
                            setShowSetupModal(false);
                            router.reload();
                        }}
                        requiresConfirmation={requiresConfirmation}
                        twoFactorEnabled={twoFactorEnabled}
                        qrCodeSvg={qrCodeSvg}
                        manualSetupKey={manualSetupKey}
                        clearSetupData={clearSetupData}
                        fetchSetupData={fetchSetupData}
                        errors={tfaErrors}
                    />

                    <ConfirmPasswordModal
                        open={showConfirmPassword}
                        onClose={() => {
                            setShowConfirmPassword(false);
                            setPendingAction(null);
                        }}
                        onConfirmed={handlePasswordConfirmed}
                    />
                </div>
            </MeLayout>
        </>
    );
}

type Passkey = {
    id: string;
    alias: string | null;
    created_at: string;
};

function getCsrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') || ''
    );
}

function PasskeysCard() {
    const [passkeys, setPasskeys] = useState<Passkey[]>([]);
    const [alias, setAlias] = useState('');
    const [loading, setLoading] = useState(true);
    const [registering, setRegistering] = useState(false);
    const [deletingId, setDeletingId] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const fetchPasskeys = useCallback(async () => {
        try {
            const response = await fetch('/me/passkeys', {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'include',
            });
            if (response.ok) {
                const data = await response.json();
                setPasskeys(data.passkeys ?? data);
            }
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchPasskeys();
    }, [fetchPasskeys]);

    const handleRegister = async () => {
        setRegistering(true);
        setError(null);
        try {
            const optionsResponse = await fetch(
                '/me/passkeys/register/options',
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'include',
                },
            );

            if (!optionsResponse.ok) {
                setError('Failed to start passkey registration.');
                return;
            }

            const options = await optionsResponse.json();

            options.challenge = base64UrlToBuffer(options.challenge);
            options.user.id = base64UrlToBuffer(options.user.id);
            if (options.excludeCredentials) {
                options.excludeCredentials = options.excludeCredentials.map(
                    (c: { id: string; type: string }) => ({
                        ...c,
                        id: base64UrlToBuffer(c.id),
                    }),
                );
            }

            const credential = (await navigator.credentials.create({
                publicKey: options,
            })) as PublicKeyCredential | null;

            if (!credential) {
                return;
            }

            const attestationResponse =
                credential.response as AuthenticatorAttestationResponse;

            const registerResponse = await fetch('/me/passkeys/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'include',
                body: JSON.stringify({
                    id: credential.id,
                    rawId: bufferToBase64Url(credential.rawId),
                    type: credential.type,
                    alias: alias || null,
                    response: {
                        clientDataJSON: bufferToBase64Url(
                            attestationResponse.clientDataJSON,
                        ),
                        attestationObject: bufferToBase64Url(
                            attestationResponse.attestationObject,
                        ),
                    },
                }),
            });

            if (registerResponse.ok) {
                setAlias('');
                await fetchPasskeys();
            } else {
                setError('Failed to save passkey.');
            }
        } catch (e) {
            if (e instanceof DOMException && e.name === 'NotAllowedError') {
                setError(
                    'Passkey registration was blocked. This may happen with self-signed certificates or if the request was cancelled.',
                );
            } else {
                setError('An error occurred during passkey registration.');
            }
        } finally {
            setRegistering(false);
        }
    };

    const handleDelete = async (id: string) => {
        setDeletingId(id);
        try {
            const response = await fetch(`/me/passkeys/${id}`, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'include',
            });

            if (response.ok) {
                setPasskeys((prev) => prev.filter((p) => p.id !== id));
            }
        } finally {
            setDeletingId(null);
        }
    };

    return (
        <div className="rounded-xl border border-border bg-card">
            <div className="p-6">
                <h2 className="text-base font-semibold">Passkeys</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Register passkeys for passwordless authentication.
                </p>

                <div className="mt-4">
                    <Label
                        htmlFor="passkey-alias"
                        className="text-sm text-muted-foreground"
                    >
                        Passkey name{' '}
                        <span className="text-muted-foreground/60">
                            (optional)
                        </span>
                    </Label>
                    <div className="mt-1.5 flex gap-3">
                        <Input
                            id="passkey-alias"
                            value={alias}
                            onChange={(e) => setAlias(e.target.value)}
                            placeholder="e.g. MacBook Pro, YubiKey"
                            className="flex-1"
                        />
                        <Button
                            size="sm"
                            onClick={handleRegister}
                            disabled={registering}
                            className="shrink-0"
                        >
                            {registering ? (
                                <Spinner />
                            ) : (
                                <Fingerprint className="size-4" />
                            )}
                            Add passkey
                        </Button>
                    </div>
                    {error && (
                        <InputError message={error} className="mt-2" />
                    )}
                </div>

                {loading ? (
                    <div className="mt-4 flex items-center justify-center gap-2 text-sm text-muted-foreground">
                        <Spinner /> Loading passkeys...
                    </div>
                ) : passkeys.length > 0 ? (
                    <div className="mt-4 space-y-3">
                        {passkeys.map((passkey) => (
                            <div
                                key={passkey.id}
                                className="flex items-center justify-between rounded-lg border border-border px-4 py-3"
                            >
                                <div className="flex items-center gap-3">
                                    <Fingerprint className="size-4 text-muted-foreground" />
                                    <div>
                                        <p className="text-sm font-medium">
                                            {passkey.alias || 'Passkey'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Added{' '}
                                            {new Date(
                                                passkey.created_at,
                                            ).toLocaleDateString()}
                                        </p>
                                    </div>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    onClick={() => handleDelete(passkey.id)}
                                    disabled={deletingId === passkey.id}
                                >
                                    {deletingId === passkey.id ? (
                                        <Spinner />
                                    ) : (
                                        <Trash2 className="size-4 text-muted-foreground" />
                                    )}
                                </Button>
                            </div>
                        ))}
                    </div>
                ) : (
                    <p className="mt-4 text-center text-sm text-muted-foreground">
                        No passkeys yet. Add one for faster, more secure
                        sign-in.
                    </p>
                )}
            </div>
        </div>
    );
}

function base64UrlToBuffer(base64url: string): ArrayBuffer {
    const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
    const padded = base64.padEnd(
        base64.length + ((4 - (base64.length % 4)) % 4),
        '=',
    );
    const binary = atob(padded);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
}

function bufferToBase64Url(buffer: ArrayBuffer): string {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.byteLength; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

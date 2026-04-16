import { useState } from 'react';

export type UseMeTwoFactorAuthReturn = {
    qrCodeSvg: string | null;
    manualSetupKey: string | null;
    recoveryCodesList: string[];
    hasSetupData: boolean;
    errors: string[];
    clearErrors: () => void;
    clearSetupData: () => void;
    fetchSetupData: () => Promise<void>;
    fetchRecoveryCodes: () => Promise<void>;
};

export const OTP_MAX_LENGTH = 6;

const getCsrfToken = (): string =>
    document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content') || '';

const fetchJson = async <T>(
    url: string,
    options?: RequestInit,
): Promise<T> => {
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCsrfToken(),
            ...(options?.headers ?? {}),
        },
        credentials: 'include',
        ...options,
    });

    if (!response.ok) {
        const data = await response.json().catch(() => null);
        throw new Error(data?.message || `Failed to fetch: ${response.status}`);
    }

    return response.json();
};

export const useMeTwoFactorAuth = (): UseMeTwoFactorAuthReturn => {
    const [qrCodeSvg, setQrCodeSvg] = useState<string | null>(null);
    const [manualSetupKey, setManualSetupKey] = useState<string | null>(null);
    const [recoveryCodesList, setRecoveryCodesList] = useState<string[]>([]);
    const [errors, setErrors] = useState<string[]>([]);

    const hasSetupData = qrCodeSvg !== null && manualSetupKey !== null;

    const clearErrors = (): void => setErrors([]);

    const clearSetupData = (): void => {
        setManualSetupKey(null);
        setQrCodeSvg(null);
        clearErrors();
    };

    const fetchQrCode = async (): Promise<void> => {
        try {
            const { svg } = await fetchJson<{ svg: string }>('/me/tfa/qr-code');
            setQrCodeSvg(svg);
        } catch {
            setErrors((prev) => [...prev, 'Failed to fetch QR code']);
            setQrCodeSvg(null);
        }
    };

    const fetchSecretKey = async (): Promise<void> => {
        try {
            const { secretKey } = await fetchJson<{ secretKey: string }>(
                '/me/tfa/secret-key',
            );
            setManualSetupKey(secretKey);
        } catch {
            setErrors((prev) => [...prev, 'Failed to fetch setup key']);
            setManualSetupKey(null);
        }
    };

    const fetchSetupData = async (): Promise<void> => {
        try {
            clearErrors();
            await Promise.all([fetchQrCode(), fetchSecretKey()]);
        } catch {
            setQrCodeSvg(null);
            setManualSetupKey(null);
        }
    };

    const fetchRecoveryCodes = async (): Promise<void> => {
        try {
            clearErrors();
            const codes = await fetchJson<string[]>('/me/tfa/recovery-codes');
            setRecoveryCodesList(codes);
        } catch {
            setErrors((prev) => [...prev, 'Failed to fetch recovery codes']);
            setRecoveryCodesList([]);
        }
    };

    return {
        qrCodeSvg,
        manualSetupKey,
        recoveryCodesList,
        hasSetupData,
        errors,
        clearErrors,
        clearSetupData,
        fetchSetupData,
        fetchRecoveryCodes,
    };
};

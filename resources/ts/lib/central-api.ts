/**
 * Lightweight fetch wrapper for the central API.
 *
 * Authentication is handled by the existing cookie-bridge middleware
 * (`InjectAccessTokenFromCookie`), so we just need to send credentials
 * and the XSRF token.
 */

export type CentralApiOptions = Omit<RequestInit, 'body'> & {
    body?: unknown;
};

export class CentralApiError extends Error {
    constructor(
        message: string,
        public status: number,
        public payload: unknown,
    ) {
        super(message);
        this.name = 'CentralApiError';
    }
}

function getCookie(name: string): string | undefined {
    if (typeof document === 'undefined') return undefined;
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop()?.split(';').shift();
    return undefined;
}

export async function centralApi<T = unknown>(
    path: string,
    options: CentralApiOptions = {},
): Promise<T> {
    const normalized = path.startsWith('/') ? path : `/${path}`;
    const url = `/api${normalized}`;

    const headers = new Headers(options.headers);
    headers.set('Accept', 'application/json');
    headers.set('X-Requested-With', 'XMLHttpRequest');
    headers.set('X-Auth-Mode', 'cookie');

    const xsrf = getCookie('XSRF-TOKEN');
    if (xsrf) {
        headers.set('X-XSRF-TOKEN', decodeURIComponent(xsrf));
    }

    let body: BodyInit | undefined;
    if (options.body !== undefined && options.body !== null) {
        if (
            options.body instanceof FormData ||
            options.body instanceof Blob ||
            typeof options.body === 'string'
        ) {
            body = options.body as BodyInit;
        } else {
            headers.set('Content-Type', 'application/json');
            body = JSON.stringify(options.body);
        }
    }

    const response = await fetch(url, {
        ...options,
        headers,
        body,
        credentials: 'include',
    });

    const contentType = response.headers.get('Content-Type') ?? '';
    const payload = contentType.includes('application/json')
        ? await response.json().catch(() => null)
        : await response.text().catch(() => null);

    if (!response.ok) {
        const message =
            (payload && typeof payload === 'object' && 'message' in payload
                ? String((payload as { message: unknown }).message)
                : null) ?? `Request failed with status ${response.status}`;
        throw new CentralApiError(message, response.status, payload);
    }

    return payload as T;
}

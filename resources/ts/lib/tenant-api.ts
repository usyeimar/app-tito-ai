/**
 * Lightweight fetch wrapper for the tenant API.
 *
 * Authentication is handled by the existing cookie-bridge middleware
 * (`InjectAccessTokenFromCookie`), so we just need to send credentials
 * and the XSRF token. The tenant slug is taken from Inertia page props
 * or, as a fallback, from the first URL segment.
 */

export type TenantApiOptions = Omit<RequestInit, 'body'> & {
    body?: unknown;
};

export class TenantApiError extends Error {
    constructor(
        message: string,
        public status: number,
        public payload: unknown,
    ) {
        super(message);
        this.name = 'TenantApiError';
    }
}

export function getTenantSlugFromUrl(): string {
    if (typeof window === 'undefined') return '';
    const segment = window.location.pathname.split('/').filter(Boolean)[0];
    return segment ?? '';
}

function getCookie(name: string): string | undefined {
    if (typeof document === 'undefined') return undefined;
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop()?.split(';').shift();
    return undefined;
}

export async function tenantApi<T = unknown>(
    tenantSlug: string,
    path: string,
    options: TenantApiOptions = {},
): Promise<T> {
    const slug = tenantSlug || getTenantSlugFromUrl();
    const normalized = path.startsWith('/') ? path : `/${path}`;
    const url = `/${slug}/api${normalized}`;

    const doFetch = async (): Promise<Response> => {
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

        return fetch(url, {
            ...options,
            headers,
            body,
            credentials: 'include',
        });
    };

    let response = await doFetch();

    // On first page visit the laravel_token cookie may not exist yet.
    // Retry once after a short delay to allow the cookie to be set.
    if (response.status === 401 && !options.method?.match(/^(PUT|PATCH|DELETE)$/i)) {
        await new Promise((r) => setTimeout(r, 500));
        response = await doFetch();
    }

    const contentType = response.headers.get('Content-Type') ?? '';
    const payload = contentType.includes('application/json')
        ? await response.json().catch(() => null)
        : await response.text().catch(() => null);

    if (!response.ok) {
        const message =
            (payload && typeof payload === 'object' && 'message' in payload
                ? String((payload as { message: unknown }).message)
                : null) ?? `Request failed with status ${response.status}`;
        throw new TenantApiError(message, response.status, payload);
    }

    return payload as T;
}

async function webFetch<T = unknown>(
    method: string,
    tenantSlug: string,
    path: string,
    body?: unknown,
): Promise<T> {
    const slug = tenantSlug || getTenantSlugFromUrl();
    const normalized = path.startsWith('/') ? path : `/${path}`;
    const url = `/${slug}${normalized}`;

    const headers = new Headers();
    headers.set('Accept', 'application/json');
    headers.set('X-Requested-With', 'XMLHttpRequest');

    const xsrf = getCookie('XSRF-TOKEN');
    if (xsrf) {
        headers.set('X-XSRF-TOKEN', decodeURIComponent(xsrf));
    }

    let requestBody: BodyInit | undefined;
    if (body !== undefined && body !== null) {
        headers.set('Content-Type', 'application/json');
        requestBody = JSON.stringify(body);
    }

    const response = await fetch(url, {
        method,
        headers,
        body: requestBody,
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
        throw new TenantApiError(message, response.status, payload);
    }

    return payload as T;
}

export const webGet = <T = unknown>(tenantSlug: string, path: string) =>
    webFetch<T>('GET', tenantSlug, path);

export const webPost = <T = unknown>(tenantSlug: string, path: string, body?: unknown) =>
    webFetch<T>('POST', tenantSlug, path, body);

export const webPatch = <T = unknown>(tenantSlug: string, path: string, body?: unknown) =>
    webFetch<T>('PATCH', tenantSlug, path, body);

export const webDelete = <T = unknown>(tenantSlug: string, path: string) =>
    webFetch<T>('DELETE', tenantSlug, path);

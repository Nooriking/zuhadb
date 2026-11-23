/** CSRF-aware JSON fetch for AI chat endpoints (session auth). */

function xsrfToken(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}

export async function aiFetch(input: string, init: RequestInit = {}): Promise<Response> {
    const headers = new Headers(init.headers);
    headers.set('Accept', 'application/json');
    headers.set('X-Requested-With', 'XMLHttpRequest');

    if (init.method && init.method.toUpperCase() !== 'GET') {
        headers.set('X-XSRF-TOKEN', xsrfToken());
        if (!headers.has('Content-Type') && init.body) {
            headers.set('Content-Type', 'application/json');
        }
    }

    return fetch(input, {
        ...init,
        credentials: 'same-origin',
        headers,
    });
}

export async function readAiError(response: Response): Promise<string> {
    const body = await response.json().catch(() => null);
    if (body?.message && typeof body.message === 'string') {
        return body.message;
    }
    if (body?.errors && typeof body.errors === 'object') {
        const first = Object.values(body.errors as Record<string, string[]>)[0];
        if (Array.isArray(first) && first[0]) {
            return first[0];
        }
    }
    if (response.status === 429) {
        return 'Too many AI requests. Please wait a moment and try again.';
    }
    if (response.status === 503) {
        return 'AI assistant is currently disabled.';
    }
    if (!response.ok) {
        return 'Unable to complete the AI request.';
    }
    return 'Something went wrong.';
}

import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { lazy, Suspense } from 'react';
import { route as ziggyRoute, type Config } from 'ziggy-js';
import { Ziggy } from './ziggy';
import { initializeTheme } from './hooks/use-appearance';

/** Static Ziggy routes — avoids dumping the full route list into every HTML response. */
const ziggyConfig = {
    ...Ziggy,
    url: typeof window !== 'undefined' ? window.location.origin : Ziggy.url,
} as Config;

const route = ((name: string, params?: unknown, absolute?: boolean) =>
    ziggyRoute(name, params as never, absolute, ziggyConfig)) as typeof ziggyRoute;

declare global {
    // eslint-disable-next-line no-var
    var route: typeof ziggyRoute;
}

globalThis.route = route;

const appName = import.meta.env.VITE_APP_NAME || 'ZUHA PERFUME';

/** Lazy pages: each route downloads only its own chunk. */
const pages = import.meta.glob('./pages/**/*.tsx');

/** Toaster is not needed for first paint — load after mount. */
const Toaster = lazy(() =>
    import('sonner').then((mod) => ({
        default: function LazyToaster() {
            return <mod.Toaster position="top-right" richColors closeButton />;
        },
    })),
);

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, pages),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <>
                <App {...props} />
                <Suspense fallback={null}>
                    <Toaster />
                </Suspense>
            </>,
        );
    },
    progress: {
        color: '#D4AF37',
        showSpinner: false,
        delay: 120,
    },
});

initializeTheme();

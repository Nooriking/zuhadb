/**
 * ESM named-export shim for Vite.
 * The package only ships CJS (`module.exports`), but recharts imports the named export.
 */
import cjs from 'use-sync-external-store-shim-cjs';

export const useSyncExternalStoreWithSelector =
    (cjs && cjs.useSyncExternalStoreWithSelector) ||
    (cjs && cjs.default && cjs.default.useSyncExternalStoreWithSelector) ||
    cjs;

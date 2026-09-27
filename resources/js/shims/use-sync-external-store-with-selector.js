/**
 * ESM named-export shim for Vite + recharts.
 * Package ships CJS only; Vite ESM cannot import the named export.
 */
import * as React from 'react';

const objectIs =
    typeof Object.is === 'function'
        ? Object.is
        : (x, y) => (x === y && (x !== 0 || 1 / x === 1 / y)) || (x !== x && y !== y);

export function useSyncExternalStoreWithSelector(
    subscribe,
    getSnapshot,
    getServerSnapshot,
    selector,
    isEqual,
) {
    const instRef = React.useRef(null);
    if (instRef.current === null) {
        instRef.current = { hasValue: false, value: null };
    }
    const inst = instRef.current;

    const [getSelection, getServerSelection] = React.useMemo(() => {
        let hasMemo = false;
        let memoizedSnapshot;
        let memoizedSelection;

        const memoizedSelector = (nextSnapshot) => {
            if (!hasMemo) {
                hasMemo = true;
                memoizedSnapshot = nextSnapshot;
                const nextSelection = selector(nextSnapshot);
                if (isEqual !== undefined) {
                    if (inst.hasValue) {
                        const currentSelection = inst.value;
                        if (isEqual(currentSelection, nextSelection)) {
                            memoizedSelection = currentSelection;
                            return currentSelection;
                        }
                    }
                }
                memoizedSelection = nextSelection;
                return nextSelection;
            }

            const prevSnapshot = memoizedSnapshot;
            const prevSelection = memoizedSelection;

            if (objectIs(prevSnapshot, nextSnapshot)) {
                return prevSelection;
            }

            const nextSelection = selector(nextSnapshot);
            if (isEqual !== undefined && isEqual(prevSelection, nextSelection)) {
                memoizedSnapshot = nextSnapshot;
                return prevSelection;
            }

            memoizedSnapshot = nextSnapshot;
            memoizedSelection = nextSelection;
            return nextSelection;
        };

        const getSnapshotWithSelector = () => memoizedSelector(getSnapshot());
        const getServerSnapshotWithSelector =
            getServerSnapshot === undefined
                ? undefined
                : () => memoizedSelector(getServerSnapshot());

        return [getSnapshotWithSelector, getServerSnapshotWithSelector];
    }, [getSnapshot, getServerSnapshot, selector, isEqual]);

    const value = React.useSyncExternalStore(subscribe, getSelection, getServerSelection);

    React.useEffect(() => {
        inst.hasValue = true;
        inst.value = value;
    }, [value]);

    React.useDebugValue(value);
    return value;
}

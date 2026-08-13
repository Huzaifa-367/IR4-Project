import { useState } from 'react';
import type { Dispatch, SetStateAction } from 'react';

/**
 * Sync local draft state when Inertia (or other) props change, without a
 * useEffect — satisfies react-hooks/set-state-in-effect.
 */
export function usePropSyncedState<T>(
    propValue: T,
): [T, Dispatch<SetStateAction<T>>] {
    const [value, setValue] = useState(propValue);
    const [prevProp, setPrevProp] = useState(propValue);

    if (propValue !== prevProp) {
        setPrevProp(propValue);
        setValue(propValue);
    }

    return [value, setValue];
}

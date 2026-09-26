// resources/js/components/dashboard/lazy-chart.tsx

import { type ReactNode } from 'react';
import { useInView } from 'react-intersection-observer';

interface Props {
    children: ReactNode;
    height?: string;
    fallback?: ReactNode;
}

export default function LazyChart({ children, height = 'h-80', fallback }: Props) {
    const { ref, inView } = useInView({
        triggerOnce: true,
        threshold: 0.1,
        rootMargin: '100px',
    });

    return (
        <div ref={ref} className={`w-full ${height}`}>
            {inView ? (
                children
            ) : (
                <div className="flex h-full w-full items-center justify-center">
                    {fallback || (
                        <div className="flex flex-col items-center gap-2">
                            <div className="border-muted border-t-foreground h-8 w-8 animate-spin rounded-full border-4" />
                            <p className="text-muted-foreground text-xs">Loading chart...</p>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

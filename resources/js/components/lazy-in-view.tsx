import { ReactNode } from 'react';
import { useInView } from 'react-intersection-observer';

/**
 * Renders children only after the block enters the viewport.
 * Use around heavy charts/widgets so they stay off the critical path.
 */
export function LazyInView({
    children,
    fallback,
    rootMargin = '160px',
    minHeight = 240,
    className,
}: {
    children: ReactNode;
    fallback?: ReactNode;
    rootMargin?: string;
    minHeight?: number;
    className?: string;
}) {
    const { ref, inView } = useInView({ triggerOnce: true, rootMargin });

    return (
        <div ref={ref} className={className} style={{ minHeight: inView ? undefined : minHeight }}>
            {inView
                ? children
                : (fallback ?? <div className="bg-muted/40 h-full min-h-[240px] animate-pulse rounded-xl" />)}
        </div>
    );
}

import * as React from 'react';
import { createPortal } from 'react-dom';

import { cn } from '@/lib/utils';

/* ═══════════════════════════════════════════════════════
 |  Context
 ═══════════════════════════════════════════════════════ */

interface PopoverContextValue {
    open: boolean;
    setOpen: (open: boolean) => void;
    triggerRef: React.RefObject<HTMLButtonElement | null>;
}

const PopoverContext = React.createContext<PopoverContextValue | null>(null);

function usePopoverContext() {
    const ctx = React.useContext(PopoverContext);
    if (!ctx) {
        throw new Error('Popover components must be used inside <Popover>');
    }
    return ctx;
}

/* ═══════════════════════════════════════════════════════
 |  Root
 ═══════════════════════════════════════════════════════ */

interface PopoverProps {
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    children: React.ReactNode;
}

function Popover({ open: controlledOpen, onOpenChange, children }: PopoverProps) {
    const [uncontrolledOpen, setUncontrolledOpen] = React.useState(false);
    const triggerRef = React.useRef<HTMLButtonElement | null>(null);

    const isControlled = controlledOpen !== undefined;
    const open = isControlled ? controlledOpen : uncontrolledOpen;

    const setOpen = React.useCallback(
        (value: boolean) => {
            if (!isControlled) {
                setUncontrolledOpen(value);
            }
            onOpenChange?.(value);
        },
        [isControlled, onOpenChange],
    );

    return <PopoverContext.Provider value={{ open, setOpen, triggerRef }}>{children}</PopoverContext.Provider>;
}

/* ═══════════════════════════════════════════════════════
 |  Trigger
 ═══════════════════════════════════════════════════════ */

interface PopoverTriggerProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    asChild?: boolean;
}

const PopoverTrigger = React.forwardRef<HTMLButtonElement, PopoverTriggerProps>(
    ({ asChild, children, onClick, ...props }, ref) => {
        const { open, setOpen, triggerRef } = usePopoverContext();

        const setRefs = (node: HTMLButtonElement | null) => {
            (triggerRef as React.MutableRefObject<HTMLButtonElement | null>).current = node;
            if (typeof ref === 'function') ref(node);
            else if (ref) (ref as React.MutableRefObject<HTMLButtonElement | null>).current = node;
        };

        const handleClick = (e: React.MouseEvent<HTMLButtonElement>) => {
            onClick?.(e);
            setOpen(!open);
        };

        // اگر asChild → به فرزند props می‌دهیم
        if (asChild && React.isValidElement(children)) {
            return React.cloneElement(children as React.ReactElement<any>, {
                ref: setRefs,
                onClick: handleClick,
                'aria-expanded': open,
                'aria-haspopup': 'dialog',
                'data-state': open ? 'open' : 'closed',
            });
        }

        return (
            <button
                ref={setRefs}
                type="button"
                onClick={handleClick}
                aria-expanded={open}
                aria-haspopup="dialog"
                data-state={open ? 'open' : 'closed'}
                {...props}
            >
                {children}
            </button>
        );
    },
);
PopoverTrigger.displayName = 'PopoverTrigger';

/* ═══════════════════════════════════════════════════════
 |  Content
 ═══════════════════════════════════════════════════════ */

interface PopoverContentProps extends React.HTMLAttributes<HTMLDivElement> {
    align?: 'start' | 'center' | 'end';
    sideOffset?: number;
    side?: 'top' | 'bottom';
}

const PopoverContent = React.forwardRef<HTMLDivElement, PopoverContentProps>(
    ({ className, align = 'center', sideOffset = 4, side = 'bottom', children, style, ...props }, ref) => {
        const { open, setOpen, triggerRef } = usePopoverContext();
        const contentRef = React.useRef<HTMLDivElement | null>(null);
        const [position, setPosition] = React.useState<{ top: number; left: number; width: number }>({
            top: 0,
            left: 0,
            width: 0,
        });

        /* ─── محاسبه موقعیت ─── */
        React.useLayoutEffect(() => {
            if (!open || !triggerRef.current) return;

            const updatePosition = () => {
                const trigger = triggerRef.current;
                if (!trigger) return;

                const rect = trigger.getBoundingClientRect();
                const scrollY = window.scrollY;
                const scrollX = window.scrollX;

                // چک می‌کنیم اگر پایین جا نیست، بالا باز شود
                const spaceBelow = window.innerHeight - rect.bottom;
                const contentHeight = contentRef.current?.offsetHeight ?? 200;
                const shouldFlip = spaceBelow < contentHeight + 20;

                let top: number;
                if (side === 'top' || shouldFlip) {
                    top = rect.top + scrollY - sideOffset - contentHeight;
                } else {
                    top = rect.bottom + scrollY + sideOffset;
                }

                let left: number;
                if (align === 'start') {
                    left = rect.left + scrollX;
                } else if (align === 'end') {
                    left = rect.right + scrollX - rect.width;
                } else {
                    left = rect.left + scrollX + rect.width / 2;
                }

                setPosition({ top, left, width: rect.width });
            };

            updatePosition();

            // بعد از رندر یک‌بار دیگر (برای گرفتن ارتفاع دقیق)
            const raf = requestAnimationFrame(updatePosition);

            window.addEventListener('scroll', updatePosition, true);
            window.addEventListener('resize', updatePosition);

            return () => {
                cancelAnimationFrame(raf);
                window.removeEventListener('scroll', updatePosition, true);
                window.removeEventListener('resize', updatePosition);
            };
        }, [open, align, sideOffset, side]);

        /* ─── بستن با کلیک بیرون ─── */
        React.useEffect(() => {
            if (!open) return;

            const handleClickOutside = (e: MouseEvent) => {
                const target = e.target as Node;
                if (
                    contentRef.current?.contains(target) ||
                    triggerRef.current?.contains(target)
                ) {
                    return;
                }
                setOpen(false);
            };

            const handleEscape = (e: KeyboardEvent) => {
                if (e.key === 'Escape') {
                    setOpen(false);
                    triggerRef.current?.focus();
                }
            };

            // با تأخیر اضافه می‌کنیم تا کلیک باز کردن trigger باعث بستن نشود
            const timer = setTimeout(() => {
                document.addEventListener('mousedown', handleClickOutside);
            }, 0);
            document.addEventListener('keydown', handleEscape);

            return () => {
                clearTimeout(timer);
                document.removeEventListener('mousedown', handleClickOutside);
                document.removeEventListener('keydown', handleEscape);
            };
        }, [open, setOpen, triggerRef]);

        if (!open) return null;

        const content = (
            <div
                ref={(node) => {
                    contentRef.current = node;
                    if (typeof ref === 'function') ref(node);
                    else if (ref) (ref as React.MutableRefObject<HTMLDivElement | null>).current = node;
                }}
                role="dialog"
                data-state="open"
                data-slot="popover-content"
                className={cn(
                    'bg-popover text-popover-foreground animate-in fade-in-0 zoom-in-95 fixed z-50 rounded-md border shadow-md outline-none',
                    className,
                )}
                style={{
                    position: 'absolute',
                    top: position.top,
                    left: align === 'center' ? position.left : position.left,
                    transform: align === 'center' ? 'translateX(-50%)' : undefined,
                    minWidth: position.width,
                    ...style,
                }}
                {...props}
            >
                {children}
            </div>
        );

        return createPortal(content, document.body);
    },
);
PopoverContent.displayName = 'PopoverContent';

/* ═══════════════════════════════════════════════════════
 |  Anchor (اختیاری — برای سازگاری API)
 ═══════════════════════════════════════════════════════ */

const PopoverAnchor = React.forwardRef<HTMLDivElement, React.HTMLAttributes<HTMLDivElement>>(
    ({ children, ...props }, ref) => (
        <div ref={ref} {...props}>
            {children}
        </div>
    ),
);
PopoverAnchor.displayName = 'PopoverAnchor';

/* ═══════════════════════════════════════════════════════
 |  Exports
 ═══════════════════════════════════════════════════════ */

export { Popover, PopoverAnchor, PopoverContent, PopoverTrigger };
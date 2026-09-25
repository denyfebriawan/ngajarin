import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

// Fades and slides its content in the first time it scrolls into view. People who ask their
// system for reduced motion (the motion-safe: variant) just see the content, unanimated.
export default function Reveal({
    children,
    delay = 0,
    className,
}: {
    children: ReactNode;
    // Milliseconds to wait, to stagger items that appear together.
    delay?: number;
    className?: string;
}) {
    const ref = useRef<HTMLDivElement>(null);
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const element = ref.current;

        if (!element) {
            return;
        }

        // The browser calls this when the element enters or leaves the viewport.
        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setVisible(true);
                    observer.disconnect(); // Animate once, not every time it scrolls past.
                }
            },
            { threshold: 0.15 },
        );

        observer.observe(element);

        // Stop watching if the component goes away first.
        return () => observer.disconnect();
    }, []);

    return (
        <div
            ref={ref}
            style={{ animationDelay: `${delay}ms` }}
            className={cn(
                visible
                    ? 'fill-mode-both motion-safe:animate-in motion-safe:animation-duration-700 motion-safe:fade-in motion-safe:slide-in-from-bottom-6'
                    : 'motion-safe:opacity-0',
                className,
            )}
        >
            {children}
        </div>
    );
}

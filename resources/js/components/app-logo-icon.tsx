import type { SVGAttributes } from 'react';

// Ngajarin's "N" monogram, drawn as one rounded stroke in the current text colour, so callers
// colour it with a text class (e.g. text-primary). public/favicon.svg is the same mark.
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
            {...props}
        >
            <path
                d="M7 17.5V6.5l10 11V6.5"
                stroke="currentColor"
                strokeWidth={2.75}
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

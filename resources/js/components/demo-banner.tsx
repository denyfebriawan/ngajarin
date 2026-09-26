import { usePage } from '@inertiajs/react';
import { Info } from 'lucide-react';

// Tells visitors using a shared demo account that they can click around freely.
export default function DemoBanner() {
    const { auth } = usePage().props;

    if (!auth.isDemo) {
        return null;
    }

    return (
        <div className="flex items-center gap-2 border-b bg-primary/10 px-4 py-2 text-sm text-primary">
            <Info className="size-4 shrink-0" />
            <p>
                You're using a shared demo account. Explore freely: everything
                resets every night.
            </p>
        </div>
    );
}

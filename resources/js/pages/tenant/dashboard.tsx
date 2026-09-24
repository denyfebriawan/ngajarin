import { Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';

export default function TenantDashboard() {
    const { currentTenant } = usePage().props;

    // Always set on tenant routes (EnsureTenantMember guarantees it); the check satisfies TypeScript.
    if (!currentTenant) {
        return null;
    }

    return (
        <>
            <Head title={currentTenant.name} />
            <div className="p-4">
                <Heading
                    title={currentTenant.name}
                    description={`You are signed in as ${currentTenant.role}.`}
                />
            </div>
        </>
    );
}

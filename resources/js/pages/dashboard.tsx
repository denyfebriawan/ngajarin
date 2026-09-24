import { Head, Link, usePage } from '@inertiajs/react';
import { Building2, Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { dashboard as tenantDashboard } from '@/routes/tenant';
import { create as createTenant } from '@/routes/tenants';

export default function Dashboard() {
    const { tenants } = usePage().props;

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                {tenants.length === 0 ? (
                    <div className="flex flex-1 flex-col items-center justify-center gap-4 rounded-xl border border-dashed p-8 text-center">
                        <Building2 className="size-10 text-muted-foreground" />
                        <Heading
                            title="Create your first workspace"
                            description="Tutors and tutoring centers get a workspace for their subjects, availability and bookings."
                        />
                        <Button asChild>
                            <Link href={createTenant()}>
                                <Plus />
                                Create a workspace
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <>
                        <Heading
                            title="Your workspaces"
                            description="Open a workspace to manage it."
                        />
                        <div className="grid gap-4 md:grid-cols-3">
                            {tenants.map((tenant) => (
                                <Link
                                    key={tenant.slug}
                                    href={tenantDashboard(tenant.slug)}
                                    className="flex items-center gap-3 rounded-xl border p-4 transition-colors hover:bg-accent"
                                >
                                    <Building2 className="size-5 text-muted-foreground" />
                                    <div>
                                        <div className="font-medium">
                                            {tenant.name}
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            /t/{tenant.slug}
                                        </div>
                                    </div>
                                </Link>
                            ))}
                            <Link
                                href={createTenant()}
                                className="flex items-center gap-3 rounded-xl border border-dashed p-4 text-muted-foreground transition-colors hover:bg-accent"
                            >
                                <Plus className="size-5" />
                                New workspace
                            </Link>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};

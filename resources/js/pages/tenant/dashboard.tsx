import { Head, Link, usePage } from '@inertiajs/react';
import { CalendarPlus } from 'lucide-react';
import BookingController from '@/actions/App/Http/Controllers/Tenant/BookingController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

export default function TenantDashboard() {
    const { currentTenant } = usePage().props;

    // Always set on tenant routes (EnsureTenantMember guarantees it); the check satisfies TypeScript.
    if (!currentTenant) {
        return null;
    }

    const bookingPage = BookingController.create(currentTenant.slug);

    return (
        <>
            <Head title={currentTenant.name} />
            <div className="space-y-6 p-4">
                <Heading
                    title={currentTenant.name}
                    description={`You are signed in as ${currentTenant.role}.`}
                />

                <div className="flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="font-medium">Booking page</div>
                        <p className="text-sm text-muted-foreground">
                            {currentTenant.can.update
                                ? 'Share this link with students so they can book lessons.'
                                : 'Book a lesson in this workspace.'}{' '}
                            <span className="font-mono">{bookingPage.url}</span>
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={bookingPage}>
                            <CalendarPlus />
                            Open booking page
                        </Link>
                    </Button>
                </div>
            </div>
        </>
    );
}

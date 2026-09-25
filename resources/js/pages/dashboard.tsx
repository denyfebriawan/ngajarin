import { Head, Link, usePage } from '@inertiajs/react';
import {
    Building2,
    CalendarDays,
    CalendarPlus,
    GraduationCap,
    Plus,
    Presentation,
} from 'lucide-react';
import BookingController from '@/actions/App/Http/Controllers/Tenant/BookingController';
import Heading from '@/components/heading';
import Reveal from '@/components/reveal';
import { Button } from '@/components/ui/button';
import { formatDayHeading } from '@/lib/format';
import { teaches } from '@/lib/roles';
import { dashboard } from '@/routes';
import { dashboard as tenantDashboard } from '@/routes/tenant';
import { index as lessonsIndex } from '@/routes/tenant/lessons';
import { create as createTenant } from '@/routes/tenants';

// One upcoming lesson, in its own workspace's timezone.
type Lesson = {
    id: number;
    tenant: { name: string; slug: string };
    subject: string;
    // True when the user teaches this lesson, false when they take it.
    teaching: boolean;
    // The other person: the student for a teacher, the teacher for a student.
    with: string;
    date: string;
    starts: string;
    ends: string;
};

export default function Dashboard({ lessons }: { lessons: Lesson[] }) {
    const { tenants } = usePage().props;

    // Workspaces split by the user's role in them.
    const teaching = tenants.filter((tenant) => teaches(tenant.role));
    const studying = tenants.filter((tenant) => !teaches(tenant.role));

    // A brand-new account: explain the two ways to use Ngajarin.
    if (tenants.length === 0) {
        return (
            <>
                <Head title="Dashboard" />
                <div className="flex flex-1 flex-col gap-6 p-4">
                    <Heading
                        title="Welcome to Ngajarin"
                        description="How will you use it?"
                    />
                    <div className="grid gap-4 md:grid-cols-2">
                        <Reveal>
                            <div className="flex h-full flex-col gap-3 rounded-xl border p-6">
                                <GraduationCap className="size-8 text-primary" />
                                <h2 className="font-semibold">I’m a student</h2>
                                <p className="text-sm text-muted-foreground">
                                    Your tutor or tutoring center will send you
                                    a link to their booking page. Open it to
                                    pick a subject and a time; your lessons will
                                    then appear here.
                                </p>
                            </div>
                        </Reveal>
                        <Reveal delay={100}>
                            <div className="flex h-full flex-col gap-3 rounded-xl border p-6">
                                <Presentation className="size-8 text-primary" />
                                <h2 className="font-semibold">I teach</h2>
                                <p className="text-sm text-muted-foreground">
                                    Create a workspace for yourself or your
                                    tutoring center, add your subjects and
                                    weekly hours, and share your booking page
                                    with students.
                                </p>
                                <Button className="mt-auto self-start" asChild>
                                    <Link href={createTenant()}>
                                        <Plus />
                                        Create a workspace
                                    </Link>
                                </Button>
                            </div>
                        </Reveal>
                    </div>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-8 p-4">
                <section className="space-y-3">
                    <Heading
                        title="Upcoming lessons"
                        description="Your next lessons in every workspace, in each workspace's local time."
                    />
                    {lessons.length === 0 ? (
                        <div className="flex items-center gap-2 rounded-xl border border-dashed p-6 text-sm text-muted-foreground">
                            <CalendarDays className="size-4" />
                            No upcoming lessons.
                        </div>
                    ) : (
                        <ul className="divide-y rounded-xl border">
                            {lessons.map((lesson) => (
                                <li key={lesson.id}>
                                    <Link
                                        href={lessonsIndex(lesson.tenant.slug)}
                                        className="flex flex-col gap-1 p-4 transition-colors hover:bg-accent sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div>
                                            <div className="font-medium">
                                                {formatDayHeading(lesson.date)},{' '}
                                                {lesson.starts}–{lesson.ends}
                                            </div>
                                            <div className="text-sm text-muted-foreground">
                                                {lesson.subject} ·{' '}
                                                {lesson.teaching
                                                    ? `student: ${lesson.with}`
                                                    : `with ${lesson.with}`}
                                            </div>
                                        </div>
                                        <span className="text-sm text-muted-foreground">
                                            {lesson.tenant.name}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {studying.length > 0 && (
                    <section className="space-y-3">
                        <Heading
                            variant="small"
                            title="My tutors"
                            description="Where you take lessons. Book another one any time."
                        />
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {studying.map((tenant) => (
                                <div
                                    key={tenant.slug}
                                    className="flex items-center justify-between gap-3 rounded-xl border p-4"
                                >
                                    <Link
                                        href={lessonsIndex(tenant.slug)}
                                        className="flex min-w-0 items-center gap-3 hover:underline"
                                    >
                                        <GraduationCap className="size-5 shrink-0 text-muted-foreground" />
                                        <span className="truncate font-medium">
                                            {tenant.name}
                                        </span>
                                    </Link>
                                    <Button size="sm" asChild>
                                        <Link
                                            href={BookingController.create(
                                                tenant.slug,
                                            )}
                                        >
                                            <CalendarPlus />
                                            Book a lesson
                                        </Link>
                                    </Button>
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                <section className="space-y-3">
                    <Heading
                        variant="small"
                        title="Teaching"
                        description={
                            teaching.length > 0
                                ? 'Workspaces where you teach or manage lessons.'
                                : 'Do you teach? Create a workspace to take bookings.'
                        }
                    />
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {teaching.map((tenant) => (
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
                                    <div className="text-sm text-muted-foreground capitalize">
                                        {tenant.role}
                                    </div>
                                </div>
                            </Link>
                        ))}
                        <Link
                            href={createTenant()}
                            className="flex items-center gap-3 rounded-xl border border-dashed p-4 text-muted-foreground transition-colors hover:bg-accent"
                        >
                            <Plus className="size-5" />
                            Create a workspace
                        </Link>
                    </div>
                </section>
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

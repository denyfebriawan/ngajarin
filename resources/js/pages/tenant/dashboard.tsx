import { Head, Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    CalendarDays,
    CalendarPlus,
    CalendarRange,
    Clock,
    Users,
    Wallet,
} from 'lucide-react';
import BookingController from '@/actions/App/Http/Controllers/Tenant/BookingController';
import Heading from '@/components/heading';
import Reveal from '@/components/reveal';
import { Button } from '@/components/ui/button';
import { formatDayHeading, formatRupiah } from '@/lib/format';
import { index as lessonsIndex } from '@/routes/tenant/lessons';

type LessonSummary = {
    id: number;
    subject: string;
    teacher: { id: number; name: string };
    student: { id: number; name: string };
    date: string;
    starts: string;
    ends: string;
};

type Props = {
    stats: {
        today: number;
        this_week: number;
        // null for everyone except owners.
        booked_this_month: number | null;
        students_this_month: number | null;
    };
    next: LessonSummary | null;
    today: LessonSummary[];
};

type Stat = { label: string; value: string; icon: LucideIcon };

export default function TenantDashboard({ stats, next, today }: Props) {
    const { currentTenant, auth } = usePage().props;

    // Always set on tenant routes (EnsureTenantMember guarantees it); the check satisfies TypeScript.
    if (!currentTenant) {
        return null;
    }

    const bookingPage = BookingController.create(currentTenant.slug);

    const cards: Stat[] = [
        { label: 'Lessons today', value: String(stats.today), icon: Clock },
        {
            label: 'Lessons this week',
            value: String(stats.this_week),
            icon: CalendarRange,
        },
        // Owner-only numbers arrive as null for everyone else, and are left out.
        ...(stats.booked_this_month !== null
            ? [
                  {
                      label: 'Booked this month',
                      value: formatRupiah(stats.booked_this_month),
                      icon: Wallet,
                  },
              ]
            : []),
        ...(stats.students_this_month !== null
            ? [
                  {
                      label: 'Students this month',
                      value: String(stats.students_this_month),
                      icon: Users,
                  },
              ]
            : []),
    ];

    // The lesson from the viewer's side, as on the lessons page.
    const who = (lesson: LessonSummary) => {
        if (lesson.student.id === auth.user?.id) {
            return `with ${lesson.teacher.name}`;
        }

        if (lesson.teacher.id === auth.user?.id) {
            return `student: ${lesson.student.name}`;
        }

        return `${lesson.teacher.name} teaching ${lesson.student.name}`;
    };

    return (
        <>
            <Head title={currentTenant.name} />

            <div className="space-y-8 p-4">
                <Heading
                    title={currentTenant.name}
                    description={`You are signed in as ${currentTenant.role}. Times are in ${currentTenant.timezone} time.`}
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {cards.map((card, index) => (
                        <Reveal key={card.label} delay={index * 80}>
                            <div className="rounded-xl border p-4">
                                <div className="flex items-center justify-between text-sm text-muted-foreground">
                                    {card.label}
                                    <card.icon className="size-4 text-primary" />
                                </div>
                                <div className="mt-2 font-display text-2xl font-bold">
                                    {card.value}
                                </div>
                            </div>
                        </Reveal>
                    ))}
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <section className="rounded-xl border p-4">
                        <h2 className="mb-3 font-semibold">Next lesson</h2>
                        {next ? (
                            <div className="rounded-lg bg-primary/10 p-4">
                                <div className="text-sm font-medium text-primary">
                                    {formatDayHeading(next.date)}, {next.starts}
                                    –{next.ends}
                                </div>
                                <div className="mt-1 font-display text-lg font-semibold">
                                    {next.subject}
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    {who(next)}
                                </div>
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No upcoming lessons.
                            </p>
                        )}
                    </section>

                    <section className="rounded-xl border p-4">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="font-semibold">Today</h2>
                            <Link
                                href={lessonsIndex(currentTenant.slug)}
                                className="text-sm text-primary underline-offset-4 hover:underline"
                            >
                                All lessons
                            </Link>
                        </div>
                        {today.length === 0 ? (
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <CalendarDays className="size-4" />
                                Nothing scheduled today.
                            </div>
                        ) : (
                            <ul className="space-y-2">
                                {today.map((lesson) => (
                                    <li
                                        key={lesson.id}
                                        className="flex items-baseline gap-3 text-sm"
                                    >
                                        <span className="w-24 shrink-0 font-medium tabular-nums">
                                            {lesson.starts}–{lesson.ends}
                                        </span>
                                        <span>
                                            {lesson.subject}{' '}
                                            <span className="text-muted-foreground">
                                                · {who(lesson)}
                                            </span>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>

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

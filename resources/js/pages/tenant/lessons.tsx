import { Head, Link, usePage } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import LessonController from '@/actions/App/Http/Controllers/Tenant/LessonController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDayHeading, formatRupiah } from '@/lib/format';

// One lesson; the date and times are already in the workspace's timezone.
type Lesson = {
    id: number;
    subject: string;
    teacher: { id: number; name: string };
    student: { id: number; name: string };
    date: string;
    starts: string;
    ends: string;
    price: number;
    status: 'confirmed' | 'cancelled';
    can_cancel: boolean;
};

export default function Lessons({
    upcoming,
    past,
}: {
    upcoming: Lesson[];
    past: Lesson[];
}) {
    const { currentTenant, auth } = usePage().props;

    if (!currentTenant) {
        return null;
    }

    // Describe the lesson from the viewer's side: their teacher, their student, or both.
    const who = (lesson: Lesson) => {
        if (lesson.student.id === auth.user?.id) {
            return `with ${lesson.teacher.name}`;
        }

        if (lesson.teacher.id === auth.user?.id) {
            return `student: ${lesson.student.name}`;
        }

        return `${lesson.teacher.name} teaching ${lesson.student.name}`;
    };

    const row = (lesson: Lesson) => (
        <li
            key={lesson.id}
            className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <div className="flex items-center gap-2 font-medium">
                    {formatDayHeading(lesson.date)}, {lesson.starts}–
                    {lesson.ends}
                    {lesson.status === 'cancelled' && (
                        <Badge variant="secondary">Cancelled</Badge>
                    )}
                </div>
                <div className="text-sm text-muted-foreground">
                    {lesson.subject} · {who(lesson)} ·{' '}
                    {formatRupiah(lesson.price)}
                </div>
            </div>
            {lesson.can_cancel && (
                <Button variant="outline" size="sm" asChild>
                    <Link
                        href={LessonController.cancel({
                            tenant: currentTenant.slug,
                            booking: lesson.id,
                        })}
                        as="button"
                        preserveScroll
                        onBefore={() =>
                            window.confirm(
                                `Cancel the lesson on ${formatDayHeading(lesson.date)} at ${lesson.starts}?`,
                            )
                        }
                    >
                        Cancel lesson
                    </Link>
                </Button>
            )}
        </li>
    );

    return (
        <>
            <Head title="Lessons" />

            <div className="mx-auto w-full max-w-3xl space-y-8 p-4">
                <Heading
                    title="Lessons"
                    description={`Times are in ${currentTenant.timezone} time.`}
                />

                <section className="space-y-3">
                    <h2 className="font-medium">Upcoming</h2>
                    {upcoming.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                            <CalendarDays className="size-10" />
                            <p>No upcoming lessons.</p>
                        </div>
                    ) : (
                        <ul className="divide-y rounded-xl border">
                            {upcoming.map(row)}
                        </ul>
                    )}
                </section>

                {past.length > 0 && (
                    <section className="space-y-3">
                        <h2 className="font-medium">Past and cancelled</h2>
                        <ul className="divide-y rounded-xl border text-muted-foreground">
                            {past.map(row)}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}

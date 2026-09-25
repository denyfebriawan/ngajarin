import { Form, Head, Link, usePage } from '@inertiajs/react';
import { CalendarX2 } from 'lucide-react';
import { useState } from 'react';
import BookingController from '@/actions/App/Http/Controllers/Tenant/BookingController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { formatDayHeading, formatDuration, formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';
import { login, register } from '@/routes';
import type { Subject } from '@/types';

type Slot = {
    // The exact moment (UTC), sent back when booking.
    starts_at: string;
    // For display, already in the workspace's timezone.
    date: string;
    time: string;
};

type Props = {
    tenant: { name: string; slug: string; timezone: string };
    subjects: Subject[];
    selected: { subject_id: number | null; teacher_id: number | null };
    slots: Slot[];
};

export default function BookLesson({
    tenant,
    subjects,
    selected,
    slots,
}: Props) {
    const { auth } = usePage().props;
    // The time the student has clicked; nothing is booked until they confirm.
    const [chosen, setChosen] = useState<Slot | null>(null);

    const subject = subjects.find((s) => s.id === selected.subject_id);
    const teacher = subject?.teachers.find((t) => t.id === selected.teacher_id);

    // Group the times by day: { "2026-10-05": [slot, slot, ...], ... }. Slots arrive sorted.
    const slotsByDate = slots.reduce<Record<string, Slot[]>>((groups, slot) => {
        (groups[slot.date] ??= []).push(slot);

        return groups;
    }, {});

    // Choices live in the URL, so each step is a link and the page can be shared or reloaded.
    const bookUrl = (query: { subject?: number; teacher?: number }) =>
        BookingController.create(tenant.slug, { query });

    return (
        <>
            <Head title={`Book a lesson with ${tenant.name}`} />

            <div className="space-y-10">
                <Heading
                    title={`Book a lesson with ${tenant.name}`}
                    description={`Times are shown in ${tenant.timezone} time.`}
                />

                {/* Step 1: the subject */}
                <section className="space-y-3">
                    <h2 className="font-medium">1. Choose a subject</h2>
                    {subjects.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            This workspace has no lessons to book yet.
                        </p>
                    )}
                    <div className="grid gap-3 sm:grid-cols-2">
                        {subjects.map((s) => (
                            <Link
                                key={s.id}
                                href={bookUrl({ subject: s.id })}
                                preserveScroll
                                className={cn(
                                    'rounded-xl border p-4 transition-colors hover:bg-accent',
                                    s.id === subject?.id &&
                                        'border-primary ring-1 ring-primary',
                                )}
                            >
                                <div className="font-medium">{s.name}</div>
                                <div className="text-sm text-muted-foreground">
                                    {formatDuration(s.duration_minutes)} ·{' '}
                                    {formatRupiah(s.price)}
                                </div>
                                <div className="mt-1 text-sm text-muted-foreground">
                                    with{' '}
                                    {s.teachers.map((t) => t.name).join(', ')}
                                </div>
                            </Link>
                        ))}
                    </div>
                </section>

                {/* Step 2: the teacher (only asked when there is a choice) */}
                {subject && subject.teachers.length > 1 && (
                    <section className="space-y-3">
                        <h2 className="font-medium">2. Choose a teacher</h2>
                        <div className="flex flex-wrap gap-2">
                            {subject.teachers.map((t) => (
                                <Button
                                    key={t.id}
                                    variant={
                                        t.id === teacher?.id
                                            ? 'default'
                                            : 'outline'
                                    }
                                    asChild
                                >
                                    <Link
                                        href={bookUrl({
                                            subject: subject.id,
                                            teacher: t.id,
                                        })}
                                        preserveScroll
                                    >
                                        {t.name}
                                    </Link>
                                </Button>
                            ))}
                        </div>
                    </section>
                )}

                {/* Step 3: the time */}
                {subject && teacher && (
                    <section className="space-y-4">
                        <h2 className="font-medium">
                            Choose a time with {teacher.name}
                        </h2>

                        {slots.length === 0 ? (
                            <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                                <CalendarX2 className="size-10" />
                                <p>No free times in the next four weeks.</p>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {Object.entries(slotsByDate).map(
                                    ([date, daySlots]) => (
                                        <div key={date}>
                                            <div className="mb-2 text-sm font-medium">
                                                {formatDayHeading(date)}
                                            </div>
                                            <div className="flex flex-wrap gap-2">
                                                {daySlots.map((slot) => (
                                                    <Button
                                                        key={slot.starts_at}
                                                        type="button"
                                                        size="sm"
                                                        variant={
                                                            chosen?.starts_at ===
                                                            slot.starts_at
                                                                ? 'default'
                                                                : 'outline'
                                                        }
                                                        onClick={() =>
                                                            setChosen(slot)
                                                        }
                                                    >
                                                        {slot.time}
                                                    </Button>
                                                ))}
                                            </div>
                                        </div>
                                    ),
                                )}
                            </div>
                        )}
                    </section>
                )}

                {/* Confirm */}
                {subject && teacher && chosen && (
                    <section className="space-y-4 rounded-xl border p-4">
                        <div>
                            <div className="font-medium">
                                {subject.name} with {teacher.name}
                            </div>
                            <div className="text-sm text-muted-foreground">
                                {formatDayHeading(chosen.date)} at {chosen.time}{' '}
                                · {formatDuration(subject.duration_minutes)} ·{' '}
                                {formatRupiah(subject.price)}
                            </div>
                        </div>

                        {auth.user ? (
                            <Form
                                {...BookingController.store.form(tenant.slug)}
                                options={{ preserveScroll: true }}
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="subject_id"
                                            value={subject.id}
                                        />
                                        <input
                                            type="hidden"
                                            name="teacher_id"
                                            value={teacher.id}
                                        />
                                        <input
                                            type="hidden"
                                            name="starts_at"
                                            value={chosen.starts_at}
                                        />
                                        <Button disabled={processing}>
                                            Book this lesson
                                        </Button>
                                        <InputError
                                            className="mt-2"
                                            message={
                                                errors.starts_at ??
                                                errors.teacher_id ??
                                                errors.subject_id
                                            }
                                        />
                                    </>
                                )}
                            </Form>
                        ) : (
                            <div className="flex flex-wrap items-center gap-2">
                                <Button asChild>
                                    <Link href={login()}>Log in to book</Link>
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={register()}>
                                        Create an account
                                    </Link>
                                </Button>
                            </div>
                        )}
                    </section>
                )}
            </div>
        </>
    );
}

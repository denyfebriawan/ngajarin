import { Head, Link, usePage } from '@inertiajs/react';
import { GraduationCap, Pencil, Plus } from 'lucide-react';
import SubjectController from '@/actions/App/Http/Controllers/Tenant/SubjectController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { formatDuration, formatRupiah } from '@/lib/format';
import type { Subject } from '@/types';

export default function SubjectsIndex({ subjects }: { subjects: Subject[] }) {
    const { currentTenant } = usePage().props;

    if (!currentTenant) {
        return null;
    }

    const canManage = currentTenant.can.manageSubjects;

    return (
        <>
            <Head title="Subjects" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Subjects"
                        description="What this workspace teaches, how long a lesson lasts and what it costs."
                    />
                    {canManage && (
                        <Button asChild>
                            <Link
                                href={SubjectController.create(
                                    currentTenant.slug,
                                )}
                            >
                                <Plus />
                                New subject
                            </Link>
                        </Button>
                    )}
                </div>

                {subjects.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                        <GraduationCap className="size-10" />
                        <p>No subjects yet.</p>
                    </div>
                ) : (
                    <ul className="divide-y rounded-xl border">
                        {subjects.map((subject) => (
                            <li
                                key={subject.id}
                                className="flex items-center justify-between gap-4 p-4"
                            >
                                <div className="min-w-0">
                                    <div className="font-medium">
                                        {subject.name}
                                    </div>
                                    {subject.description && (
                                        <p className="truncate text-sm text-muted-foreground">
                                            {subject.description}
                                        </p>
                                    )}
                                </div>
                                <div className="flex shrink-0 items-center gap-4 text-sm">
                                    <span className="text-muted-foreground">
                                        {formatDuration(
                                            subject.duration_minutes,
                                        )}
                                    </span>
                                    <span className="font-medium">
                                        {formatRupiah(subject.price)}
                                    </span>
                                    {canManage && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            asChild
                                        >
                                            <Link
                                                href={SubjectController.edit({
                                                    tenant: currentTenant.slug,
                                                    subject: subject.id,
                                                })}
                                                aria-label={`Edit ${subject.name}`}
                                            >
                                                <Pencil />
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

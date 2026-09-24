import { Head, Link, usePage } from '@inertiajs/react';
import SubjectController from '@/actions/App/Http/Controllers/Tenant/SubjectController';
import Heading from '@/components/heading';
import SubjectForm from '@/components/subject-form';
import { Button } from '@/components/ui/button';
import type { Subject, Teacher } from '@/types';

export default function EditSubject({
    subject,
    teachers,
}: {
    subject: Subject;
    teachers: Teacher[];
}) {
    const { currentTenant } = usePage().props;

    if (!currentTenant) {
        return null;
    }

    const route = { tenant: currentTenant.slug, subject: subject.id };

    return (
        <>
            <Head title={`Edit ${subject.name}`} />

            <div className="mx-auto w-full max-w-xl space-y-10 p-4">
                <div>
                    <Heading title={`Edit ${subject.name}`} />
                    <SubjectForm
                        form={SubjectController.update.form(route)}
                        subject={subject}
                        teachers={teachers}
                        submitLabel="Save"
                    />
                </div>

                <div className="space-y-4 rounded-xl border border-destructive/30 p-4">
                    <Heading
                        variant="small"
                        title="Delete this subject"
                        description="Students will no longer be able to book it."
                    />
                    <Button variant="destructive" asChild>
                        <Link
                            href={SubjectController.destroy(route)}
                            as="button"
                            onBefore={() =>
                                window.confirm(`Delete ${subject.name}?`)
                            }
                        >
                            Delete subject
                        </Link>
                    </Button>
                </div>
            </div>
        </>
    );
}

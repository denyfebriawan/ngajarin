import { Head, usePage } from '@inertiajs/react';
import SubjectController from '@/actions/App/Http/Controllers/Tenant/SubjectController';
import Heading from '@/components/heading';
import SubjectForm from '@/components/subject-form';
import type { Teacher } from '@/types';

export default function CreateSubject({ teachers }: { teachers: Teacher[] }) {
    const { currentTenant } = usePage().props;

    if (!currentTenant) {
        return null;
    }

    return (
        <>
            <Head title="New subject" />

            <div className="mx-auto w-full max-w-xl p-4">
                <Heading
                    title="New subject"
                    description="Students choose a subject when they book a lesson."
                />
                <SubjectForm
                    form={SubjectController.store.form(currentTenant.slug)}
                    teachers={teachers}
                    submitLabel="Create subject"
                />
            </div>
        </>
    );
}

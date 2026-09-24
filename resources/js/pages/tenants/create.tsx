import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import TenantController from '@/actions/App/Http/Controllers/TenantController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create } from '@/routes/tenants';

// "Budi's Math Center" -> "budis-math-center": the same format the server's validation expects.
function slugify(text: string): string {
    return text
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .slice(0, 50)
        .replace(/^-+|-+$/g, '');
}

export default function CreateTenant() {
    const [slug, setSlug] = useState('');
    const [slugEdited, setSlugEdited] = useState(false);

    return (
        <>
            <Head title="Create a workspace" />

            <div className="mx-auto w-full max-w-xl p-4">
                <Heading
                    title="Create a workspace"
                    description="Your workspace is where you manage subjects, availability and bookings."
                />

                <Form {...TenantController.store.form()} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Workspace name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    autoFocus
                                    placeholder="Budi's Math Center"
                                    onChange={(event) => {
                                        if (!slugEdited) {
                                            setSlug(
                                                slugify(event.target.value),
                                            );
                                        }
                                    }}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="slug">Web address</Label>
                                <div className="flex items-center gap-2">
                                    <span className="text-sm text-muted-foreground">
                                        /t/
                                    </span>
                                    <Input
                                        id="slug"
                                        name="slug"
                                        required
                                        value={slug}
                                        placeholder="budis-math-center"
                                        onChange={(event) => {
                                            setSlug(event.target.value);
                                            setSlugEdited(true);
                                        }}
                                    />
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    Students will book lessons at this address.
                                    It can't be changed later.
                                </p>
                                <InputError message={errors.slug} />
                            </div>

                            <Button disabled={processing}>
                                Create workspace
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

CreateTenant.layout = {
    breadcrumbs: [
        {
            title: 'Create a workspace',
            href: create(),
        },
    ],
};

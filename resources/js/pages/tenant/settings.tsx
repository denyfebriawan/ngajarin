import { Form, Head, usePage } from '@inertiajs/react';
import TenantSettingsController from '@/actions/App/Http/Controllers/Tenant/TenantSettingsController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function TenantSettings({
    timezones,
}: {
    // IANA name -> label, e.g. { "Asia/Jakarta": "WIB: Western Indonesia (...)" }.
    timezones: Record<string, string>;
}) {
    const { currentTenant } = usePage().props;

    // Always set on tenant routes (EnsureTenantMember guarantees it); the check satisfies TypeScript.
    if (!currentTenant) {
        return null;
    }

    return (
        <>
            <Head title="Workspace settings" />

            <div className="mx-auto w-full max-w-xl p-4">
                <Heading
                    title="Workspace settings"
                    description="Change how your workspace appears to students."
                />

                <Form
                    {...TenantSettingsController.update.form(
                        currentTenant.slug,
                    )}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Workspace name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    defaultValue={currentTenant.name}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="timezone">Timezone</Label>
                                <select
                                    id="timezone"
                                    name="timezone"
                                    defaultValue={currentTenant.timezone}
                                    className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                                >
                                    {Object.entries(timezones).map(
                                        ([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ),
                                    )}
                                </select>
                                <p className="text-sm text-muted-foreground">
                                    Teachers' weekly hours are in this timezone.
                                </p>
                                <InputError message={errors.timezone} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="slug">Web address</Label>
                                <Input
                                    id="slug"
                                    value={`/t/${currentTenant.slug}`}
                                    disabled
                                />
                                <p className="text-sm text-muted-foreground">
                                    The address can't be changed, so links you
                                    have shared keep working.
                                </p>
                            </div>

                            <Button disabled={processing}>Save</Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

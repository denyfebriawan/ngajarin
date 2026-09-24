import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Plane, Trash2 } from 'lucide-react';
import TimeOffController from '@/actions/App/Http/Controllers/Tenant/TimeOffController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/format';

// One period of leave. Dates are "YYYY-MM-DD" in the workspace's timezone; both days are included.
type TimeOffEntry = {
    id: number;
    start_date: string;
    end_date: string;
    reason: string | null;
};

export default function TimeOff({
    entries,
    today,
}: {
    entries: TimeOffEntry[];
    today: string;
}) {
    const { currentTenant } = usePage().props;

    if (!currentTenant) {
        return null;
    }

    return (
        <>
            <Head title="Time off" />

            <div className="mx-auto w-full max-w-2xl space-y-8 p-4">
                <Heading
                    title="Time off"
                    description="Days you are away, such as holidays or leave. Students cannot book you on these days, even inside your weekly hours."
                />

                <Form
                    {...TimeOffController.store.form(currentTenant.slug)}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="space-y-4 rounded-xl border p-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="start_date">
                                        First day
                                    </Label>
                                    <Input
                                        id="start_date"
                                        name="start_date"
                                        type="date"
                                        required
                                        min={today}
                                    />
                                    <InputError message={errors.start_date} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="end_date">Last day</Label>
                                    <Input
                                        id="end_date"
                                        name="end_date"
                                        type="date"
                                        required
                                        min={today}
                                    />
                                    <InputError message={errors.end_date} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="reason">
                                    Reason (optional)
                                </Label>
                                <Input
                                    id="reason"
                                    name="reason"
                                    maxLength={255}
                                    placeholder="Family holiday"
                                />
                                <InputError message={errors.reason} />
                            </div>

                            <Button disabled={processing}>Add time off</Button>
                        </>
                    )}
                </Form>

                {entries.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                        <Plane className="size-10" />
                        <p>No upcoming time off.</p>
                    </div>
                ) : (
                    <ul className="divide-y rounded-xl border">
                        {entries.map((entry) => {
                            const dates =
                                entry.start_date === entry.end_date
                                    ? formatDate(entry.start_date)
                                    : `${formatDate(entry.start_date)} – ${formatDate(entry.end_date)}`;

                            return (
                                <li
                                    key={entry.id}
                                    className="flex items-center justify-between gap-4 p-4"
                                >
                                    <div>
                                        <div className="font-medium">
                                            {dates}
                                        </div>
                                        {entry.reason && (
                                            <p className="text-sm text-muted-foreground">
                                                {entry.reason}
                                            </p>
                                        )}
                                    </div>
                                    <Button variant="ghost" size="icon" asChild>
                                        <Link
                                            href={TimeOffController.destroy({
                                                tenant: currentTenant.slug,
                                                timeOff: entry.id,
                                            })}
                                            as="button"
                                            preserveScroll
                                            aria-label={`Remove time off ${dates}`}
                                            onBefore={() =>
                                                window.confirm(
                                                    `Remove time off ${dates}?`,
                                                )
                                            }
                                        >
                                            <Trash2 />
                                        </Link>
                                    </Button>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </>
    );
}

import { Head, useForm, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import AvailabilityController from '@/actions/App/Http/Controllers/Tenant/AvailabilityController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

// One block of weekly hours. Times are "HH:MM", local to the workspace's timezone.
type Block = {
    weekday: number;
    starts_at: string;
    ends_at: string;
};

// ISO weekdays, matching the server: 1 = Monday ... 7 = Sunday.
const WEEKDAYS = [
    { value: 1, label: 'Monday' },
    { value: 2, label: 'Tuesday' },
    { value: 3, label: 'Wednesday' },
    { value: 4, label: 'Thursday' },
    { value: 5, label: 'Friday' },
    { value: 6, label: 'Saturday' },
    { value: 7, label: 'Sunday' },
];

export default function Availability({ blocks }: { blocks: Block[] }) {
    const { currentTenant } = usePage().props;
    // The form's state lives in React, so rows can be added and removed before saving.
    const form = useForm({ blocks });

    if (!currentTenant) {
        return null;
    }

    const addBlock = (weekday: number) =>
        form.setData('blocks', [
            ...form.data.blocks,
            { weekday, starts_at: '09:00', ends_at: '12:00' },
        ]);

    const removeBlock = (index: number) =>
        form.setData(
            'blocks',
            form.data.blocks.filter((_, i) => i !== index),
        );

    const updateBlock = (
        index: number,
        field: 'starts_at' | 'ends_at',
        value: string,
    ) =>
        form.setData(
            'blocks',
            form.data.blocks.map((block, i) =>
                i === index ? { ...block, [field]: value } : block,
            ),
        );

    const save = (event: FormEvent) => {
        event.preventDefault();
        form.submit(AvailabilityController.update(currentTenant.slug), {
            preserveScroll: true,
            // What was just saved becomes the new "unchanged" state.
            onSuccess: () => form.setDefaults(),
        });
    };

    return (
        <>
            <Head title="My availability" />

            <form
                onSubmit={save}
                className="mx-auto w-full max-w-2xl space-y-6 p-4"
            >
                <Heading
                    title="My availability"
                    description={`The hours you can teach each week, in ${currentTenant.timezone} time. Students can only book inside these hours.`}
                />

                <InputError message={form.errors.blocks} />

                <div className="divide-y rounded-xl border">
                    {WEEKDAYS.map((day) => {
                        // Keep each block's position in the full list: errors and updates use it.
                        const dayBlocks = form.data.blocks
                            .map((block, index) => ({ block, index }))
                            .filter(({ block }) => block.weekday === day.value);

                        return (
                            <div
                                key={day.value}
                                className="flex flex-col gap-3 p-4 sm:flex-row sm:items-start"
                            >
                                <div className="w-28 pt-2 font-medium">
                                    {day.label}
                                </div>

                                <div className="flex-1 space-y-2">
                                    {dayBlocks.length === 0 && (
                                        <p className="pt-2 text-sm text-muted-foreground">
                                            Unavailable
                                        </p>
                                    )}

                                    {dayBlocks.map(({ block, index }) => (
                                        <div key={index}>
                                            <div className="flex items-center gap-2">
                                                <Input
                                                    type="time"
                                                    step={900}
                                                    aria-label={`${day.label} start`}
                                                    value={block.starts_at}
                                                    onChange={(event) =>
                                                        updateBlock(
                                                            index,
                                                            'starts_at',
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="w-32"
                                                />
                                                <span className="text-muted-foreground">
                                                    to
                                                </span>
                                                <Input
                                                    type="time"
                                                    step={900}
                                                    aria-label={`${day.label} end`}
                                                    value={block.ends_at}
                                                    onChange={(event) =>
                                                        updateBlock(
                                                            index,
                                                            'ends_at',
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="w-32"
                                                />
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Remove ${day.label} ${block.starts_at} to ${block.ends_at}`}
                                                    onClick={() =>
                                                        removeBlock(index)
                                                    }
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </div>
                                            <InputError
                                                message={
                                                    form.errors[
                                                        `blocks.${index}.starts_at`
                                                    ] ??
                                                    form.errors[
                                                        `blocks.${index}.ends_at`
                                                    ] ??
                                                    form.errors[
                                                        `blocks.${index}.weekday`
                                                    ]
                                                }
                                            />
                                        </div>
                                    ))}

                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => addBlock(day.value)}
                                    >
                                        <Plus />
                                        Add hours
                                    </Button>
                                </div>
                            </div>
                        );
                    })}
                </div>

                <div className="flex items-center gap-4">
                    <Button disabled={form.processing || !form.isDirty}>
                        Save availability
                    </Button>
                    {form.isDirty && (
                        <span className="text-sm text-muted-foreground">
                            You have unsaved changes.
                        </span>
                    )}
                </div>
            </form>
        </>
    );
}

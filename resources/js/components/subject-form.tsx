import { Form } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Subject } from '@/types';

type SubjectFormProps = {
    // Where the form submits: a Wayfinder `.form()` result (action + method).
    form: Pick<ComponentProps<typeof Form>, 'action' | 'method'>;
    // Present when editing: fills the fields with the current values.
    subject?: Subject;
    submitLabel: string;
};

// Shared by the create and edit pages, which differ only in where they submit and the defaults.
export default function SubjectForm({
    form,
    subject,
    submitLabel,
}: SubjectFormProps) {
    return (
        <Form {...form} className="space-y-6">
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            required
                            maxLength={100}
                            defaultValue={subject?.name}
                            placeholder="Math Grade 10"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">
                            Description (optional)
                        </Label>
                        <textarea
                            id="description"
                            name="description"
                            rows={3}
                            maxLength={1000}
                            defaultValue={subject?.description ?? ''}
                            className="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                        />
                        <InputError message={errors.description} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="duration_minutes">
                                Lesson length (minutes)
                            </Label>
                            <Input
                                id="duration_minutes"
                                name="duration_minutes"
                                type="number"
                                required
                                min={15}
                                max={480}
                                step={5}
                                defaultValue={subject?.duration_minutes ?? 60}
                            />
                            <InputError message={errors.duration_minutes} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="price">Price per lesson (Rp)</Label>
                            <Input
                                id="price"
                                name="price"
                                type="number"
                                required
                                min={0}
                                step={1}
                                defaultValue={subject?.price}
                                placeholder="150000"
                            />
                            <InputError message={errors.price} />
                        </div>
                    </div>

                    <Button disabled={processing}>{submitLabel}</Button>
                </>
            )}
        </Form>
    );
}

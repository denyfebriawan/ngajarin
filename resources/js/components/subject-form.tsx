import { Form } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Subject, Teacher } from '@/types';

type SubjectFormProps = {
    // Where the form submits: a Wayfinder `.form()` result (action + method).
    form: Pick<ComponentProps<typeof Form>, 'action' | 'method'>;
    // Everyone who can be picked as a teacher in this workspace.
    teachers: Teacher[];
    // Present when editing: fills the fields with the current values.
    subject?: Subject;
    submitLabel: string;
};

// Shared by the create and edit pages, which differ only in where they submit and the defaults.
export default function SubjectForm({
    form,
    teachers,
    subject,
    submitLabel,
}: SubjectFormProps) {
    // Ids of the teachers already linked to this subject, to pre-tick their boxes.
    const selectedIds = new Set(
        subject?.teachers.map((teacher) => teacher.id) ?? [],
    );

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

                    <fieldset className="grid gap-3">
                        <legend className="mb-3 text-sm font-medium">
                            Who teaches it
                        </legend>
                        {teachers.map((teacher) => (
                            <div
                                key={teacher.id}
                                className="flex items-center gap-3"
                            >
                                <Checkbox
                                    id={`teacher-${teacher.id}`}
                                    name="teacher_ids[]"
                                    value={String(teacher.id)}
                                    defaultChecked={selectedIds.has(teacher.id)}
                                />
                                <Label htmlFor={`teacher-${teacher.id}`}>
                                    {teacher.name}
                                </Label>
                            </div>
                        ))}
                        <InputError
                            message={
                                // An error for one ticked teacher is keyed "teacher_ids.0", "teacher_ids.1", ...
                                Object.entries(errors).find(([field]) =>
                                    field.startsWith('teacher_ids'),
                                )?.[1]
                            }
                        />
                    </fieldset>

                    <Button disabled={processing}>{submitLabel}</Button>
                </>
            )}
        </Form>
    );
}

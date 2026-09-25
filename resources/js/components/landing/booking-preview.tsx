import { CheckCircle2 } from 'lucide-react';
import { cn } from '@/lib/utils';

const times = [
    '09:00',
    '09:30',
    '10:00',
    '10:30',
    '11:00',
    '13:00',
    '13:30',
    '14:00',
];
const chosen = '10:00';

// A picture of the booking page for the landing page. It is decorative: plain elements styled
// like the real UI (not buttons, so nothing can be clicked or focused), described to screen
// readers as a single image.
export default function BookingPreview() {
    return (
        <div
            role="img"
            aria-label="Preview of a booking page: a student choosing Monday 10:00 for Math Grade 10, confirmed with a 'Lesson booked' message."
            className="relative mx-auto w-full max-w-md select-none"
        >
            <div className="rounded-2xl border bg-background p-5 shadow-2xl shadow-primary/15">
                <div className="flex items-center justify-between">
                    <div>
                        <div className="text-xs text-muted-foreground">
                            Book a lesson with
                        </div>
                        <div className="font-display font-semibold">
                            Budi Math Center
                        </div>
                    </div>
                    <div className="flex size-9 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
                        BS
                    </div>
                </div>

                <div className="mt-4 rounded-xl border border-primary bg-primary/5 p-3 ring-1 ring-primary">
                    <div className="text-sm font-medium">Math Grade 10</div>
                    <div className="text-xs text-muted-foreground">
                        1 h · Rp 150.000 · with Budi Santoso
                    </div>
                </div>

                <div className="mt-4 text-xs font-medium">Mon 5 Oct</div>
                <div className="mt-2 grid grid-cols-4 gap-2">
                    {times.map((time) => (
                        <div
                            key={time}
                            className={cn(
                                'rounded-md border py-1.5 text-center text-xs font-medium',
                                time === chosen
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {time}
                        </div>
                    ))}
                </div>

                <div className="mt-5 rounded-md bg-primary py-2 text-center text-sm font-medium text-primary-foreground">
                    Book this lesson
                </div>
            </div>

            {/* The confirmation, floating over the corner of the card. */}
            <div className="absolute -right-3 -bottom-5 flex items-center gap-2 rounded-xl border bg-background px-4 py-3 text-sm shadow-xl motion-safe:animate-float sm:-right-6">
                <CheckCircle2 className="size-5 text-primary" />
                <div>
                    <div className="font-medium">Lesson booked</div>
                    <div className="text-xs text-muted-foreground">
                        Mon 5 Oct, 10:00
                    </div>
                </div>
            </div>
        </div>
    );
}

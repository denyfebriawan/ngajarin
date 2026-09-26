import { Link, usePage } from '@inertiajs/react';
import { GraduationCap, Presentation } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { login as demoLogin } from '@/routes/demo';

// One-click logins to the shared demo accounts, so visitors can look around without signing up.
// Renders nothing when the demo is switched off or someone is already signed in.
export default function DemoLoginButtons({
    className,
}: {
    className?: string;
}) {
    const { auth, demoEnabled } = usePage().props;

    if (!demoEnabled || auth.user) {
        return null;
    }

    return (
        <div className={cn('flex flex-wrap gap-2', className)}>
            <Button variant="outline" asChild>
                <Link href={demoLogin('tutor')} as="button">
                    <Presentation />
                    Try as a tutor
                </Link>
            </Button>
            <Button variant="outline" asChild>
                <Link href={demoLogin('student')} as="button">
                    <GraduationCap />
                    Try as a student
                </Link>
            </Button>
        </div>
    );
}

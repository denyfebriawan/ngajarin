import { Head, Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    CalendarCheck2,
    CalendarClock,
    Link2,
    MapPin,
    ShieldCheck,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import { dashboard, home, login, register } from '@/routes';

type Feature = {
    icon: LucideIcon;
    title: string;
    description: string;
};

const features: Feature[] = [
    {
        icon: Link2,
        title: 'Your own booking page',
        description:
            'Every workspace gets a link to share with students. They pick a subject, a teacher and a free time, and book in a few clicks.',
    },
    {
        icon: ShieldCheck,
        title: 'Never double-booked',
        description:
            'Two students clicking the same slot at the same moment? Only one gets it. The rule is enforced by the database itself, not just the app.',
    },
    {
        icon: CalendarClock,
        title: 'Weekly hours and time off',
        description:
            'Each teacher sets the hours they teach every week and blocks out holidays. Students only ever see times that are really free.',
    },
    {
        icon: MapPin,
        title: 'Built for Indonesia',
        description:
            'Prices in rupiah, and weekly hours in your own timezone, whether that is WIB, WITA or WIT.',
    },
];

const steps = [
    {
        title: 'Create your workspace',
        description:
            'Sign up and name your workspace: yourself as a private tutor, or your tutoring center.',
    },
    {
        title: 'Add subjects and hours',
        description:
            'Set each subject’s lesson length and price, choose who teaches it, and enter your weekly hours.',
    },
    {
        title: 'Share your link',
        description:
            'Send your booking page to students. Booked lessons appear on your lessons page straight away.',
    },
];

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Online lesson booking for tutors" />

            <div className="min-h-screen bg-background text-foreground">
                <header className="border-b">
                    <div className="mx-auto flex h-14 max-w-5xl items-center justify-between px-4">
                        <Link href={home()} className="flex items-center">
                            <AppLogo />
                        </Link>
                        <nav className="flex items-center gap-2">
                            {auth.user ? (
                                <Button asChild>
                                    <Link href={dashboard()}>Dashboard</Link>
                                </Button>
                            ) : (
                                <>
                                    <Button variant="ghost" asChild>
                                        <Link href={login()}>Log in</Link>
                                    </Button>
                                    <Button asChild>
                                        <Link href={register()}>Sign up</Link>
                                    </Button>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                <main>
                    {/* Hero */}
                    <section className="mx-auto max-w-5xl px-4 py-20 text-center sm:py-28">
                        <p className="mb-4 text-sm font-medium text-muted-foreground">
                            For private tutors and tutoring centers
                        </p>
                        <h1 className="mx-auto max-w-3xl text-4xl font-semibold tracking-tight sm:text-5xl">
                            Let students book your lessons online
                        </h1>
                        <p className="mx-auto mt-6 max-w-2xl text-lg text-muted-foreground">
                            Set your subjects, prices and weekly hours. Students
                            pick a free time and book it, and no teacher is ever
                            booked twice for the same time.
                        </p>
                        <div className="mt-10 flex flex-wrap justify-center gap-3">
                            <Button size="lg" asChild>
                                <Link
                                    href={auth.user ? dashboard() : register()}
                                >
                                    <CalendarCheck2 />
                                    {auth.user
                                        ? 'Go to your dashboard'
                                        : 'Create your workspace'}
                                </Link>
                            </Button>
                            <Button size="lg" variant="outline" asChild>
                                <a href="#how-it-works">See how it works</a>
                            </Button>
                        </div>
                    </section>

                    {/* Features */}
                    <section className="border-y bg-muted/40">
                        <div className="mx-auto grid max-w-5xl gap-6 px-4 py-16 sm:grid-cols-2">
                            {features.map((feature) => (
                                <div
                                    key={feature.title}
                                    className="rounded-xl border bg-background p-6"
                                >
                                    <feature.icon className="mb-4 size-6 text-primary" />
                                    <h2 className="font-semibold">
                                        {feature.title}
                                    </h2>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        {feature.description}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </section>

                    {/* How it works */}
                    <section
                        id="how-it-works"
                        className="mx-auto max-w-5xl scroll-mt-8 px-4 py-20"
                    >
                        <h2 className="text-center text-3xl font-semibold tracking-tight">
                            How it works
                        </h2>
                        <ol className="mt-12 grid gap-8 sm:grid-cols-3">
                            {steps.map((step, index) => (
                                <li key={step.title}>
                                    <div className="mb-4 flex size-10 items-center justify-center rounded-full bg-primary font-semibold text-primary-foreground">
                                        {index + 1}
                                    </div>
                                    <h3 className="font-semibold">
                                        {step.title}
                                    </h3>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        {step.description}
                                    </p>
                                </li>
                            ))}
                        </ol>
                        {!auth.user && (
                            <div className="mt-14 text-center">
                                <Button size="lg" asChild>
                                    <Link href={register()}>
                                        Get started, it’s free
                                    </Link>
                                </Button>
                            </div>
                        )}
                    </section>
                </main>

                <footer className="border-t">
                    <div className="mx-auto flex max-w-5xl flex-col items-center justify-between gap-2 px-4 py-6 text-sm text-muted-foreground sm:flex-row">
                        <p>Built with Laravel, React and PostgreSQL.</p>
                        <a
                            href="https://github.com/denyfebriawan/ngajarin"
                            className="underline-offset-4 hover:underline"
                            target="_blank"
                            rel="noreferrer"
                        >
                            Source code on GitHub
                        </a>
                    </div>
                </footer>
            </div>
        </>
    );
}

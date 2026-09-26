import { Head, Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    CalendarCheck2,
    CalendarClock,
    Link2,
    ShieldCheck,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import DemoLoginButtons from '@/components/demo-login-buttons';
import BookingPreview from '@/components/landing/booking-preview';
import Reveal from '@/components/reveal';
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
    const { auth, demoEnabled, name } = usePage().props;
    const description =
        'Ngajarin gives private tutors and tutoring centers a booking page: students pick a free time and book, and no teacher is ever double-booked.';

    return (
        <>
            {/* Brand first on the home page. The description is the summary search engines and
                link previews (WhatsApp, LinkedIn) show under the title. */}
            <Head title={`${name} — Online lesson booking for tutors`}>
                <meta
                    head-key="description"
                    name="description"
                    content={description}
                />
                <meta
                    head-key="og:title"
                    property="og:title"
                    content={`${name} — Online lesson booking for tutors`}
                />
                <meta
                    head-key="og:description"
                    property="og:description"
                    content={description}
                />
            </Head>

            <div className="min-h-screen bg-background text-foreground">
                <header className="sticky top-0 z-10 border-b bg-background/80 backdrop-blur">
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
                    {/* Hero, with a soft brand-coloured glow behind it */}
                    <section className="relative isolate overflow-hidden">
                        <div
                            aria-hidden="true"
                            className="pointer-events-none absolute inset-x-0 -top-40 -z-10 flex justify-center"
                        >
                            <div className="size-160 rounded-full bg-primary/20 blur-3xl" />
                        </div>

                        <div className="mx-auto grid max-w-6xl items-center gap-16 px-4 py-20 sm:py-24 lg:grid-cols-2 lg:gap-12">
                            <div className="text-center lg:text-left">
                                <Reveal>
                                    <p className="mb-4 inline-flex rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-sm font-medium text-primary">
                                        For private tutors and tutoring centers
                                    </p>
                                </Reveal>
                                <Reveal delay={100}>
                                    <h1 className="mx-auto max-w-3xl text-4xl font-bold sm:text-5xl lg:mx-0 xl:text-6xl">
                                        Let students book your lessons{' '}
                                        <span className="bg-linear-to-r from-primary to-emerald-500 bg-clip-text text-transparent">
                                            online
                                        </span>
                                    </h1>
                                </Reveal>
                                <Reveal delay={200}>
                                    <p className="mx-auto mt-6 max-w-2xl text-lg text-muted-foreground lg:mx-0">
                                        Set your subjects, prices and weekly
                                        hours. Students pick a free time and
                                        book it, and no teacher is ever booked
                                        twice for the same time.
                                    </p>
                                </Reveal>
                                <Reveal
                                    delay={300}
                                    className="mt-10 flex flex-wrap justify-center gap-3 lg:justify-start"
                                >
                                    <Button
                                        size="lg"
                                        className="shadow-lg shadow-primary/25 transition-transform hover:-translate-y-0.5"
                                        asChild
                                    >
                                        <Link
                                            href={
                                                auth.user
                                                    ? dashboard()
                                                    : register()
                                            }
                                        >
                                            <CalendarCheck2 />
                                            {auth.user
                                                ? 'Go to your dashboard'
                                                : 'Create your workspace'}
                                        </Link>
                                    </Button>
                                    <Button size="lg" variant="outline" asChild>
                                        <a href="#how-it-works">
                                            See how it works
                                        </a>
                                    </Button>
                                </Reveal>
                                {demoEnabled && !auth.user && (
                                    <Reveal
                                        delay={400}
                                        className="mt-6 flex flex-col items-center gap-2 lg:items-start"
                                    >
                                        <p className="text-sm text-muted-foreground">
                                            Or look around first, no sign-up
                                            needed:
                                        </p>
                                        <DemoLoginButtons />
                                    </Reveal>
                                )}
                            </div>

                            <Reveal delay={250} className="lg:pl-6">
                                <BookingPreview />
                            </Reveal>
                        </div>
                    </section>

                    {/* Features */}
                    <section className="border-y bg-muted/40">
                        <div className="mx-auto grid max-w-5xl gap-6 px-4 py-16 md:grid-cols-3">
                            {features.map((feature, index) => (
                                <Reveal key={feature.title} delay={index * 120}>
                                    <div className="group h-full rounded-xl border bg-background p-6 transition duration-300 hover:-translate-y-1 hover:border-primary/40 hover:shadow-lg hover:shadow-primary/10">
                                        <div className="mb-4 inline-flex rounded-lg bg-primary/10 p-2.5 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                                            <feature.icon className="size-5" />
                                        </div>
                                        <h2 className="font-semibold">
                                            {feature.title}
                                        </h2>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {feature.description}
                                        </p>
                                    </div>
                                </Reveal>
                            ))}
                        </div>
                    </section>

                    {/* How it works */}
                    <section
                        id="how-it-works"
                        className="mx-auto max-w-5xl scroll-mt-20 px-4 py-20"
                    >
                        <Reveal>
                            <h2 className="text-center text-3xl font-semibold tracking-tight">
                                How it works
                            </h2>
                        </Reveal>
                        <ol className="mt-12 grid gap-8 sm:grid-cols-3">
                            {steps.map((step, index) => (
                                <li key={step.title}>
                                    <Reveal delay={index * 150}>
                                        <div className="mb-4 flex size-10 items-center justify-center rounded-full bg-primary font-semibold text-primary-foreground shadow-md shadow-primary/25">
                                            {index + 1}
                                        </div>
                                        <h3 className="font-semibold">
                                            {step.title}
                                        </h3>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {step.description}
                                        </p>
                                    </Reveal>
                                </li>
                            ))}
                        </ol>
                        {!auth.user && (
                            <Reveal className="mt-14 text-center">
                                <Button
                                    size="lg"
                                    className="shadow-lg shadow-primary/25 transition-transform hover:-translate-y-0.5"
                                    asChild
                                >
                                    <Link href={register()}>
                                        Get started, it’s free
                                    </Link>
                                </Button>
                            </Reveal>
                        )}
                    </section>
                </main>

                <footer className="border-t">
                    <div className="mx-auto flex max-w-5xl flex-col items-center justify-between gap-2 px-4 py-6 text-sm text-muted-foreground sm:flex-row">
                        <p>Built with Laravel, React and PostgreSQL.</p>
                        <a
                            href="https://github.com/denyfebriawan/ngajarin"
                            className="underline-offset-4 hover:text-primary hover:underline"
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

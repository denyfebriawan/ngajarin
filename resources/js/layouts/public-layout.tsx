import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import { dashboard, home, login, register } from '@/routes';

// For pages anyone can open, such as a workspace's booking page: no sidebar, which assumes a
// signed-in user, just a header with sign-in links for guests.
export default function PublicLayout({ children }: { children: ReactNode }) {
    const { auth } = usePage().props;

    return (
        <div className="min-h-screen bg-background">
            <header className="border-b">
                <div className="mx-auto flex h-14 max-w-4xl items-center justify-between px-4">
                    <Link href={home()} className="flex items-center">
                        <AppLogo />
                    </Link>
                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <Button variant="ghost" asChild>
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
            <main className="mx-auto max-w-4xl px-4 py-8">{children}</main>
        </div>
    );
}

import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import BrandLogo from '@/Components/BrandLogo';

const menuItems = [
    { name: 'Dashboard', href: '/dashboard' },
    { name: 'History', href: '/history' },
    { name: 'Results', href: '/results' },
    { name: 'Referral', href: '/referral' },
    { name: 'Deposit', href: '/deposit' },
    { name: 'Withdrawal', href: '/withdrawal' },
];

export default function AuthenticatedLayout({ children }) {
    const page = usePage();
    const user = page.props.auth.user;
    const [menuOpen, setMenuOpen] = useState(false);

    const mobileLabel = user?.country_code
        ? `${user.country_code} ${user.mobile}`
        : user?.email;

    useEffect(() => {
        setMenuOpen(false);
    }, [page.url]);

    useEffect(() => {
        const onKeyDown = (e) => {
            if (e.key === 'Escape') {
                setMenuOpen(false);
            }
        };

        document.addEventListener('keydown', onKeyDown);
        return () => document.removeEventListener('keydown', onKeyDown);
    }, []);

    useEffect(() => {
        document.body.style.overflow = menuOpen ? 'hidden' : '';
        return () => {
            document.body.style.overflow = '';
        };
    }, [menuOpen]);

    const goTo = (href) => {
        setMenuOpen(false);
        router.visit(href);
    };

    return (
        <div className="min-h-screen bg-navy-dark text-app-text">
            <header className="fixed inset-x-0 top-0 z-[120] h-14 border-b border-white/10 bg-navy sm:h-16">
                <div className="mx-auto flex h-full max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
                    <Link
                        href="/dashboard"
                        onClick={() => setMenuOpen(false)}
                    >
                        <BrandLogo
                            variant="mark"
                            size="md"
                            showWordmark
                            className="[&>span:last-child]:text-lg"
                        />
                    </Link>

                    <div className="flex items-center gap-2 sm:gap-3">
                        <div className="hidden text-right sm:block">
                            <p className="max-w-[140px] truncate text-sm font-medium leading-tight text-white">
                                {user?.name}
                            </p>
                            <p className="max-w-[140px] truncate text-[11px] text-app-muted">
                                {mobileLabel}
                            </p>
                        </div>

                        <button
                            type="button"
                            aria-label={menuOpen ? 'Close menu' : 'Open menu'}
                            aria-expanded={menuOpen}
                            onClick={() => setMenuOpen((open) => !open)}
                            className={`inline-flex h-10 w-10 items-center justify-center rounded-xl transition ${
                                menuOpen
                                    ? 'bg-gold text-navy-dark'
                                    : 'bg-white/10 text-white ring-1 ring-white/15 hover:bg-white/15'
                            }`}
                        >
                            <span className="sr-only">Menu</span>
                            {menuOpen ? (
                                <svg
                                    className="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="2.2"
                                    strokeLinecap="round"
                                >
                                    <path d="M6 6l12 12M18 6L6 18" />
                                </svg>
                            ) : (
                                <svg
                                    className="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="2.2"
                                    strokeLinecap="round"
                                >
                                    <path d="M4 7h16M4 12h16M4 17h16" />
                                </svg>
                            )}
                        </button>
                    </div>
                </div>
            </header>

            <div className="h-14 sm:h-16" aria-hidden="true" />

            {menuOpen && (
                <>
                    <div
                        className="fixed inset-x-0 bottom-0 top-14 z-[110] bg-navy-dark/70 sm:top-16"
                        onClick={() => setMenuOpen(false)}
                        aria-hidden="true"
                    />

                    <div className="fixed end-3 top-[3.75rem] z-[115] w-[min(17.5rem,calc(100vw-1.5rem))] overflow-hidden rounded-2xl bg-card shadow-xl ring-1 ring-white/10 sm:end-6 sm:top-[4.25rem] lg:end-8">
                        <div className="border-b border-white/10 bg-blue/40 px-4 py-3 sm:hidden">
                            <p className="truncate text-sm font-semibold text-white">
                                {user?.name}
                            </p>
                            <p className="truncate text-xs text-app-muted">
                                {mobileLabel}
                            </p>
                        </div>

                        <nav className="p-2">
                            {menuItems.map((item) => {
                                const active = page.url.startsWith(item.href);
                                return (
                                    <button
                                        key={item.href}
                                        type="button"
                                        onClick={() => goTo(item.href)}
                                        className={`mb-0.5 flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-left text-sm font-medium transition ${
                                            active
                                                ? 'bg-gold text-navy-dark'
                                                : 'text-app-text hover:bg-white/5'
                                        }`}
                                    >
                                        <span>{item.name}</span>
                                        {active && (
                                            <span className="text-[10px] uppercase tracking-wide text-navy-dark/70">
                                                Active
                                            </span>
                                        )}
                                    </button>
                                );
                            })}

                            <div className="mt-1 border-t border-white/10 pt-1">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setMenuOpen(false);
                                        router.post('/logout');
                                    }}
                                    className="flex w-full items-center rounded-xl px-3 py-2.5 text-left text-sm font-medium text-danger transition hover:bg-danger/10"
                                >
                                    Log Out
                                </button>
                            </div>
                        </nav>
                    </div>
                </>
            )}

            <main>{children}</main>
        </div>
    );
}

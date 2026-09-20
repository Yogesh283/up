import { Link } from '@inertiajs/react';

export default function AuthLayout({ title, subtitle, children, compact = false }) {
    if (compact) {
        return (
            <div className="auth-shell relative flex min-h-dvh items-start justify-center bg-navy px-4 pb-6 pt-14 sm:items-center sm:pt-6">
                <div
                    className="auth-glow pointer-events-none absolute inset-0"
                    aria-hidden="true"
                />
                <div
                    className="auth-grid pointer-events-none absolute inset-0 opacity-25"
                    aria-hidden="true"
                />

                <div className="auth-panel relative z-10 w-full max-w-[320px]">
                    <div className="mb-3 flex justify-center">
                        <Link
                            href={route('login')}
                            className="inline-flex items-center gap-2 text-white"
                        >
                            <span className="flex h-8 w-8 items-center justify-center rounded-xl bg-gold text-xs font-bold text-navy-dark">
                                L
                            </span>
                            <span className="font-display text-lg font-semibold tracking-tight">
                                Lottery
                            </span>
                        </Link>
                    </div>

                    <div className="auth-compact rounded-2xl bg-card p-4 ring-1 ring-white/10 shadow-[0_20px_50px_-20px_rgba(0,0,0,0.65)]">
                        {title && (
                            <div className="mb-3 space-y-0.5 text-center">
                                <h2 className="font-display text-lg font-semibold tracking-tight text-white sm:text-xl">
                                    {title}
                                </h2>
                                {subtitle && (
                                    <p className="text-[11px] leading-relaxed text-app-muted">
                                        {subtitle}
                                    </p>
                                )}
                            </div>
                        )}
                        {children}
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="auth-shell relative flex min-h-dvh flex-col bg-navy lg:min-h-screen lg:flex-row lg:overflow-hidden">
            <div
                className="auth-glow pointer-events-none absolute inset-0"
                aria-hidden="true"
            />
            <div
                className="auth-grid pointer-events-none absolute inset-0 opacity-25 lg:opacity-30"
                aria-hidden="true"
            />

            <aside className="relative z-10 hidden w-[46%] flex-col justify-between px-12 py-10 text-white lg:flex xl:px-16">
                <Link
                    href={route('login')}
                    className="auth-fade-in inline-flex items-center gap-3"
                >
                    <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-gold text-lg font-bold tracking-tight text-navy-dark">
                        L
                    </span>
                    <span className="font-display text-2xl font-semibold tracking-tight">
                        Lottery
                    </span>
                </Link>

                <div className="auth-fade-up max-w-md space-y-5">
                    <p className="text-xs font-semibold uppercase tracking-[0.28em] text-gold">
                        Secure access
                    </p>
                    <h1 className="font-display text-5xl font-semibold leading-[1.05] tracking-tight">
                        Play with confidence.
                    </h1>
                    <p className="text-base leading-relaxed text-app-muted">
                        Sign in to manage draws, tickets, and your account in one
                        place.
                    </p>
                </div>

                <div
                    className="auth-float flex gap-3 text-app-muted"
                    aria-hidden="true"
                >
                    {['01', '18', '27', '36'].map((n) => (
                        <span
                            key={n}
                            className="flex h-12 w-12 items-center justify-center rounded-full border border-gold/30 bg-gold/10 font-display text-sm tracking-wide text-gold"
                        >
                            {n}
                        </span>
                    ))}
                </div>
            </aside>

            <main className="relative z-10 flex flex-1 flex-col px-4 pb-[max(2rem,env(safe-area-inset-bottom))] pt-[max(1.5rem,env(safe-area-inset-top))] sm:px-6 sm:pb-10 sm:pt-8 lg:w-[54%] lg:justify-center lg:bg-navy-dark lg:px-12 lg:py-10">
                <div className="mx-auto flex w-full max-w-[400px] flex-1 flex-col lg:max-w-md lg:flex-none">
                    <div className="mb-5 flex shrink-0 items-center justify-between lg:hidden">
                        <Link
                            href={route('login')}
                            className="inline-flex items-center gap-2.5 text-white"
                        >
                            <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-gold text-sm font-bold text-navy-dark">
                                L
                            </span>
                            <span className="font-display text-xl font-semibold tracking-tight">
                                Lottery
                            </span>
                        </Link>
                    </div>

                    <div className="auth-panel flex flex-1 flex-col rounded-2xl bg-card p-6 ring-1 ring-white/10 shadow-[0_20px_50px_-20px_rgba(0,0,0,0.65)] sm:p-8 lg:bg-transparent lg:p-0 lg:shadow-none lg:ring-0">
                        {title && (
                            <div className="mb-6 space-y-1.5 sm:mb-8 sm:space-y-2">
                                <h2 className="font-display text-[1.65rem] font-semibold tracking-tight text-white sm:text-3xl">
                                    {title}
                                </h2>
                                {subtitle && (
                                    <p className="text-sm leading-relaxed text-app-muted">
                                        {subtitle}
                                    </p>
                                )}
                            </div>
                        )}
                        <div className="flex flex-1 flex-col">{children}</div>
                    </div>
                </div>
            </main>
        </div>
    );
}

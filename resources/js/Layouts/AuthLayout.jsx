import { Link } from '@inertiajs/react';
import BrandLogo from '@/Components/BrandLogo';

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
                        <Link href={route('login')} className="text-white">
                            <BrandLogo
                                variant="mark"
                                size="sm"
                                showWordmark
                                className="[&>span:last-child]:text-lg"
                            />
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
                <Link href={route('login')} className="auth-fade-in">
                    <BrandLogo
                        variant="mark"
                        size="lg"
                        showWordmark
                        className="[&>span:last-child]:text-2xl"
                    />
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
                    className="auth-float flex items-center gap-3"
                    aria-hidden="true"
                >
                    <img
                        src="/images/brand/logo-up.png"
                        alt=""
                        className="h-12 w-12 rounded-xl object-cover ring-1 ring-gold/30"
                    />
                    <img
                        src="/images/brand/logo-down.png"
                        alt=""
                        className="h-12 w-12 rounded-xl object-cover ring-1 ring-gold/30"
                    />
                </div>
            </aside>

            <main className="relative z-10 flex flex-1 flex-col px-4 pb-[max(2rem,env(safe-area-inset-bottom))] pt-[max(1.5rem,env(safe-area-inset-top))] sm:px-6 sm:pb-10 sm:pt-8 lg:w-[54%] lg:justify-center lg:bg-navy-dark lg:px-12 lg:py-10">
                <div className="mx-auto flex w-full max-w-[400px] flex-1 flex-col lg:max-w-md lg:flex-none">
                    <div className="mb-5 flex shrink-0 items-center justify-between lg:hidden">
                        <Link href={route('login')} className="text-white">
                            <BrandLogo
                                variant="mark"
                                size="md"
                                showWordmark
                                className="[&>span:last-child]:text-xl"
                            />
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

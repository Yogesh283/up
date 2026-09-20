import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import { formatDateTime } from '@/lib/format';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { useCallback, useEffect, useMemo, useState } from 'react';

function CaseCell({ label, caseNo, value, ank }) {
    return (
        <div className="rounded-xl bg-navy-dark/60 px-2 py-3 text-center ring-1 ring-white/10">
            <p className="text-[10px] font-semibold uppercase tracking-[0.18em] text-gold/80">
                Case {caseNo}
            </p>
            <p className="mt-1 text-[10px] uppercase tracking-wide text-app-muted">
                {label}
            </p>
            <p className="mt-1.5 font-display text-base font-semibold text-gold sm:text-lg">
                {value || '***'}
            </p>
            {ank ? (
                <p className="mt-0.5 text-[10px] text-app-muted">Ank {ank}</p>
            ) : null}
        </div>
    );
}

function ThreeCases({ result, large = false }) {
    const open = result.cases?.open;
    const jodi = result.cases?.jodi;
    const close = result.cases?.close;

    return (
        <div className={`grid grid-cols-3 gap-2 ${large ? 'sm:gap-3' : ''}`}>
            <CaseCell
                caseNo={1}
                label={open?.label || 'Open'}
                value={open?.display || result.open_pana}
                ank={open?.ank || result.open_ank}
            />
            <CaseCell
                caseNo={2}
                label={jodi?.label || 'Jodi'}
                value={jodi?.display || result.jodi}
            />
            <CaseCell
                caseNo={3}
                label={close?.label || 'Close'}
                value={close?.display || result.close_pana}
                ank={close?.ank || result.close_ank}
            />
        </div>
    );
}

function formatDay(date) {
    if (!date) return '';
    try {
        return new Date(`${date}T12:00:00`).toLocaleDateString(undefined, {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    } catch {
        return date;
    }
}

function MarketCard({ result }) {
    return (
        <article className="rounded-2xl bg-card p-4 shadow-sm ring-1 ring-white/10 sm:p-5">
            <div className="mb-3 flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="truncate text-sm font-semibold text-white">
                            {result.name}
                        </p>
                        {result.is_india ? (
                            <span className="rounded-full bg-gold/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gold ring-1 ring-gold/25">
                                India
                            </span>
                        ) : null}
                        {result.is_complete ? (
                            <span className="rounded-full bg-success/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-success ring-1 ring-success/25">
                                Full
                            </span>
                        ) : null}
                    </div>
                    <p className="mt-0.5 text-xs text-app-muted">
                        {[
                            result.date ? formatDay(result.date) : null,
                            result.open_time && result.close_time
                                ? `${result.open_time} – ${result.close_time}`
                                : null,
                            result.status_label || result.status,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                    <p className="mt-1 font-display text-sm font-semibold tracking-wide text-white">
                        {result.full_result || '***-***-***'}
                    </p>
                </div>
                <p className="shrink-0 rounded-full bg-white/5 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-gold ring-1 ring-gold/20">
                    {result.status}
                </p>
            </div>
            <ThreeCases result={result} />
        </article>
    );
}

export default function Result() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [tab, setTab] = useState('india');

    const load = useCallback(() => {
        const url =
            typeof route === 'function'
                ? route('api.results')
                : '/api/results';

        return axios
            .get(url)
            .then((response) => {
                setData(response.data);
                setError(
                    response.data?.error
                        ? `API warning: ${response.data.error}`
                        : null,
                );
            })
            .catch((err) => {
                setError(
                    err.response?.data?.message ||
                        'Could not load live results. Please try again.',
                );
            })
            .finally(() => setLoading(false));
    }, []);

    useEffect(() => {
        setLoading(true);
        load();
        const timer = setInterval(load, 30000);
        return () => clearInterval(timer);
    }, [load]);

    const rows = useMemo(() => {
        if (!data) return [];
        if (tab === 'india') return data.india_results || [];
        if (tab === 'declared') return data.declared || [];
        if (tab === 'other') return data.other_results || [];
        return data.results || [];
    }, [data, tab]);

    return (
        <AuthenticatedLayout>
            <Head title="Results" />

            <div className="mx-auto max-w-6xl px-4 py-6 pb-24 sm:px-6 sm:py-8 sm:pb-8 lg:px-8">
                <PageHeader
                    eyebrow="India board"
                    title="Results"
                    subtitle={
                        data?.date
                            ? `India markets on top · full Open / Jodi / Close · ${formatDay(data.date)}`
                            : 'India markets on top with full three-case results.'
                    }
                />

                <div className="mb-4 flex flex-wrap items-center gap-2">
                    {[
                        {
                            id: 'india',
                            label: 'India',
                            count: data?.counts?.india,
                        },
                        {
                            id: 'declared',
                            label: 'Declared',
                            count: data?.counts?.declared,
                        },
                        {
                            id: 'all',
                            label: 'All',
                            count: data?.counts?.total,
                        },
                        {
                            id: 'other',
                            label: 'Other',
                            count: data?.counts?.other,
                        },
                    ].map((item) => (
                        <button
                            key={item.id}
                            type="button"
                            onClick={() => setTab(item.id)}
                            className={`rounded-xl px-3 py-1.5 text-xs font-semibold transition ${
                                tab === item.id
                                    ? 'bg-gold text-navy-dark'
                                    : 'bg-white/5 text-app-muted ring-1 ring-white/10 hover:bg-white/10'
                            }`}
                        >
                            {item.label}
                            {item.count != null ? ` (${item.count})` : ''}
                        </button>
                    ))}
                    <button
                        type="button"
                        onClick={() => {
                            setLoading(true);
                            load();
                        }}
                        className="ms-auto rounded-xl bg-white/5 px-3 py-1.5 text-xs font-semibold text-white ring-1 ring-white/10 hover:bg-white/10"
                    >
                        Refresh
                    </button>
                </div>

                {error && (
                    <div className="mb-4 rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
                        {error}
                    </div>
                )}

                {!loading && data?.latest && (
                    <section className="mb-6 overflow-hidden rounded-2xl bg-card p-5 text-white shadow-sm ring-1 ring-gold/25 sm:p-6">
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-white/60">
                                Last result
                            </p>
                            {data.latest.is_india ? (
                                <span className="rounded-full bg-gold/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gold ring-1 ring-gold/25">
                                    India
                                </span>
                            ) : null}
                        </div>
                        <h2 className="mt-2 font-display text-xl font-semibold">
                            {data.latest.name}
                        </h2>
                        <p className="mt-1 text-sm text-white/70">
                            {[
                                data.latest.date
                                    ? formatDay(data.latest.date)
                                    : null,
                                data.latest.open_time && data.latest.close_time
                                    ? `${data.latest.open_time} – ${data.latest.close_time}`
                                    : null,
                                data.latest.status,
                                data.latest.drawn_at
                                    ? formatDateTime(data.latest.drawn_at)
                                    : null,
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        </p>
                        <p className="mt-3 font-display text-2xl font-semibold tracking-wide text-gold">
                            {data.latest.full_result || '***-***-***'}
                        </p>
                        <div className="mt-4">
                            <ThreeCases result={data.latest} large />
                        </div>
                    </section>
                )}

                <div className="space-y-3">
                    {loading
                        ? [1, 2, 3, 4].map((i) => (
                              <div
                                  key={i}
                                  className="h-36 animate-pulse rounded-2xl bg-card ring-1 ring-white/10"
                              />
                          ))
                        : rows.map((result) => (
                              <MarketCard key={result.id} result={result} />
                          ))}

                    {!loading && rows.length === 0 && !error && (
                        <p className="rounded-2xl bg-card px-4 py-8 text-center text-sm text-app-muted ring-1 ring-white/10">
                            Is filter mein abhi koi market nahi mila.
                        </p>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

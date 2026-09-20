import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { useCallback, useEffect, useMemo, useState } from 'react';

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

function pad2(value) {
    const digits = String(value ?? '').replace(/\D/g, '');
    if (!digits) return null;
    if (digits.length === 1) return digits.padStart(2, '0');
    return digits.slice(0, 2);
}

/** Screenshot-style left / right numbers (XX if pending). */
function rowNumbers(result) {
    let left = pad2(result.open_ank);
    let right = pad2(result.close_ank);

    const raw = String(result.result_string || result.full_result || '');
    const parts = raw
        .split(/[-–]/)
        .map((p) => p.trim())
        .filter(Boolean);

    if (parts.length >= 1 && !left) {
        const openPart = parts[0].replace(/\*/g, '');
        left = pad2(openPart) || pad2(result.open_pana);
    }

    if (parts.length >= 3 && !right) {
        const closePart = parts[2].replace(/\*/g, '');
        if (closePart && !parts[2].includes('*')) {
            right = pad2(closePart) || pad2(result.close_pana);
        }
    } else if (parts.length === 2 && !right) {
        const second = parts[1].replace(/\*/g, '');
        if (second && !parts[1].includes('*') && second.length >= 2) {
            right = pad2(second);
        }
    }

    const jodi = String(result.jodi || '');
    if (!left && /^\d{2}$/.test(jodi)) {
        left = jodi;
    }

    return {
        left: left || 'XX',
        right: right || 'XX',
    };
}

function chartUrl(result) {
    if (!result?.slug) return 'https://sattamatkaapi.live/live-results';
    return `https://sattamatkaapi.live/live-results`;
}

function ResultRow({ result, index }) {
    const { left, right } = rowNumbers(result);
    const highlight = Boolean(result.is_india || result.has_result);
    const timeLabel = result.close_time || result.open_time || '—';

    return (
        <div
            className={`flex items-center gap-2 border-b border-black/10 px-3 py-3 sm:gap-3 sm:px-4 ${
                highlight ? 'bg-[#ffe566]' : index % 2 === 0 ? 'bg-white' : 'bg-neutral-100'
            }`}
        >
            <span className="w-7 shrink-0 text-sm font-bold text-black/70 sm:w-8 sm:text-base">
                {index + 1}.
            </span>

            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-bold uppercase leading-tight tracking-wide text-black sm:text-base">
                    {result.name}
                </p>
                <p className="mt-0.5 text-[11px] text-black/80 sm:text-xs">
                    at {timeLabel}{' '}
                    <a
                        href={chartUrl(result)}
                        target="_blank"
                        rel="noreferrer"
                        className="font-medium text-blue-600 underline"
                    >
                        Record Chart
                    </a>
                </p>
            </div>

            <div className="flex shrink-0 items-center gap-4 pr-1 sm:gap-8 sm:pr-2">
                <span className="w-10 text-center text-xl font-bold tabular-nums text-black sm:w-12 sm:text-2xl">
                    {left}
                </span>
                <span className="w-10 text-center text-xl font-bold tabular-nums text-black sm:w-12 sm:text-2xl">
                    {right}
                </span>
            </div>
        </div>
    );
}

export default function Result() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [search, setSearch] = useState('');

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
        const list = data?.results || data?.india_results || [];
        const q = search.trim().toLowerCase();
        if (!q) return list;
        return list.filter((row) =>
            String(row.name || '')
                .toLowerCase()
                .includes(q),
        );
    }, [data, search]);

    const latest = data?.latest;
    const latestNums = latest ? rowNumbers(latest) : null;

    return (
        <AuthenticatedLayout>
            <Head title="Results" />

            <div className="mx-auto max-w-6xl px-4 py-6 pb-24 sm:px-6 sm:py-8 sm:pb-8 lg:px-8">
                <PageHeader
                    eyebrow="Live board"
                    title="Results"
                    subtitle={
                        data?.date
                            ? `Search markets · numbered list · ${formatDay(data.date)}`
                            : 'Search markets and view last results.'
                    }
                />

                {error && (
                    <div className="mb-4 rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
                        {error}
                    </div>
                )}

                {!loading && latest && (
                    <section className="mb-4 overflow-hidden rounded-2xl bg-[#ffe566] p-4 text-black shadow-sm ring-1 ring-black/10">
                        <p className="text-[10px] font-bold uppercase tracking-[0.2em] text-black/60">
                            Last result
                        </p>
                        <div className="mt-2 flex items-center gap-3">
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-base font-bold uppercase">
                                    {latest.name}
                                </p>
                                <p className="text-xs text-black/70">
                                    at {latest.close_time || latest.open_time || '—'}
                                </p>
                            </div>
                            <span className="text-2xl font-bold tabular-nums">
                                {latestNums.left}
                            </span>
                            <span className="text-2xl font-bold tabular-nums">
                                {latestNums.right}
                            </span>
                        </div>
                    </section>
                )}

                <div className="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                    <label className="relative block min-w-0 flex-1">
                        <span className="sr-only">Search results</span>
                        <input
                            type="search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search market name…"
                            className="w-full rounded-xl border-0 bg-white px-4 py-3 text-sm text-black outline-none ring-1 ring-white/20 placeholder:text-neutral-500 focus:ring-2 focus:ring-gold"
                        />
                    </label>
                    <button
                        type="button"
                        onClick={() => {
                            setLoading(true);
                            load();
                        }}
                        className="rounded-xl bg-gold px-4 py-3 text-sm font-semibold text-navy-dark"
                    >
                        Refresh
                    </button>
                </div>

                <div className="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-white/10">
                    <div className="flex items-center gap-2 border-b border-black/10 bg-neutral-200 px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-black/60 sm:gap-3 sm:px-4">
                        <span className="w-7 sm:w-8">#</span>
                        <span className="min-w-0 flex-1">Market</span>
                        <div className="flex shrink-0 items-center gap-4 pr-1 sm:gap-8 sm:pr-2">
                            <span className="w-10 text-center sm:w-12">Open</span>
                            <span className="w-10 text-center sm:w-12">Close</span>
                        </div>
                    </div>

                    {loading
                        ? [1, 2, 3, 4, 5].map((i) => (
                              <div
                                  key={i}
                                  className="h-16 animate-pulse border-b border-black/5 bg-neutral-100"
                              />
                          ))
                        : rows.map((result, index) => (
                              <ResultRow
                                  key={result.id}
                                  result={result}
                                  index={index}
                              />
                          ))}

                    {!loading && rows.length === 0 && (
                        <p className="px-4 py-10 text-center text-sm text-neutral-500">
                            {search
                                ? `“${search}” se koi market nahi mila.`
                                : 'Abhi koi result nahi mila.'}
                        </p>
                    )}
                </div>

                {!loading && data?.counts && (
                    <p className="mt-3 text-center text-xs text-app-muted">
                        Showing {rows.length} · India {data.counts.india} ·
                        Declared {data.counts.declared}
                    </p>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

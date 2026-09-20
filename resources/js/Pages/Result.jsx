import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { useCallback, useEffect, useMemo, useState } from 'react';

function chartUrl(result, board) {
    if (result?.chart_url) return result.chart_url;
    return board === 'matka'
        ? 'https://sattakalyanmatka.net/'
        : 'https://satta-king-fast.com/';
}

function KingRow({ result, index, yesterdayLabel, todayLabel }) {
    const highlight = Boolean(
        result.is_featured ||
            result.highlight ||
            (result.today_result && result.today_result !== 'XX'),
    );
    const timeLabel = result.close_time || result.open_time || '—';

    return (
        <div
            className={`flex items-center gap-2 border-b border-black/10 px-3 py-3 sm:gap-3 sm:px-4 ${
                highlight
                    ? 'bg-[#ffe566]'
                    : index % 2 === 0
                      ? 'bg-white'
                      : 'bg-neutral-100'
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
                        href={chartUrl(result, 'king')}
                        target="_blank"
                        rel="noreferrer"
                        className="font-medium text-blue-600 underline"
                    >
                        Record Chart
                    </a>
                </p>
            </div>
            <div className="flex shrink-0 items-center gap-4 pr-1 sm:gap-8 sm:pr-2">
                <span className="w-12 text-center text-xl font-bold tabular-nums text-black sm:w-14 sm:text-2xl">
                    {result.last_result || 'XX'}
                </span>
                <span className="w-12 text-center text-xl font-bold tabular-nums text-black sm:w-14 sm:text-2xl">
                    {result.today_result || 'XX'}
                </span>
            </div>
        </div>
    );
}

function MatkaRow({ result, index }) {
    const highlight = Boolean(result.is_featured || result.is_complete);
    const timeLabel = result.close_time || result.open_time || '—';
    const open = result.cases?.open?.display || result.open_pana || '***';
    const jodi = result.cases?.jodi?.display || result.jodi || '***';
    const close = result.cases?.close?.display || result.close_pana || '***';

    return (
        <div
            className={`border-b border-black/10 px-3 py-3 sm:px-4 ${
                highlight
                    ? 'bg-[#ffe566]'
                    : index % 2 === 0
                      ? 'bg-white'
                      : 'bg-neutral-100'
            }`}
        >
            <div className="mb-2 flex items-start gap-2">
                <span className="w-7 shrink-0 text-sm font-bold text-black/70 sm:w-8">
                    {index + 1}.
                </span>
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-bold uppercase text-black sm:text-base">
                        {result.name}
                    </p>
                    <p className="mt-0.5 text-[11px] text-black/80">
                        {timeLabel}{' '}
                        <a
                            href={chartUrl(result, 'matka')}
                            target="_blank"
                            rel="noreferrer"
                            className="font-medium text-blue-600 underline"
                        >
                            Chart
                        </a>
                    </p>
                    <p className="mt-1 font-display text-base font-semibold tracking-wide text-black">
                        {result.full_result || 'XX'}
                    </p>
                </div>
                <span className="shrink-0 rounded-full bg-black/5 px-2 py-1 text-[10px] font-semibold uppercase text-black/60">
                    {result.status_label || result.status}
                </span>
            </div>
            <div className="ms-7 grid grid-cols-3 gap-2 sm:ms-8">
                <div className="rounded-lg bg-black/5 px-2 py-2 text-center">
                    <p className="text-[10px] uppercase text-black/50">Open</p>
                    <p className="font-bold tabular-nums text-black">{open}</p>
                </div>
                <div className="rounded-lg bg-black/5 px-2 py-2 text-center">
                    <p className="text-[10px] uppercase text-black/50">Jodi</p>
                    <p className="font-bold tabular-nums text-black">{jodi}</p>
                </div>
                <div className="rounded-lg bg-black/5 px-2 py-2 text-center">
                    <p className="text-[10px] uppercase text-black/50">Close</p>
                    <p className="font-bold tabular-nums text-black">{close}</p>
                </div>
            </div>
        </div>
    );
}

export default function Result() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [search, setSearch] = useState('');
    const [board, setBoard] = useState('king');

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
        const list =
            board === 'matka'
                ? data?.matka_results || []
                : data?.king_results || data?.results || [];
        const q = search.trim().toLowerCase();
        if (!q) return list;
        return list.filter((row) =>
            String(row.name || '')
                .toLowerCase()
                .includes(q),
        );
    }, [data, search, board]);

    const latest =
        board === 'matka' ? data?.latest_matka : data?.latest;
    const yesterdayLabel =
        data?.king?.yesterday_label || data?.yesterday_label || 'Last';
    const todayLabel = data?.king?.today_label || data?.today_label || 'Today';
    const banner =
        board === 'matka'
            ? data?.matka_banner_text || data?.matka?.banner_text
            : data?.banner_text;

    return (
        <AuthenticatedLayout>
            <Head title="Results" />

            <div className="mx-auto max-w-6xl px-4 py-6 pb-24 sm:px-6 sm:py-8 sm:pb-8 lg:px-8">
                <PageHeader
                    eyebrow="Live boards"
                    title="Results"
                    subtitle="Satta King Fast + Kalyan Matka — dono sources."
                />

                {error && (
                    <div className="mb-4 rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
                        {error}
                    </div>
                )}

                <div className="mb-3 flex flex-wrap gap-2">
                    {[
                        {
                            id: 'king',
                            label: 'Satta King',
                            count: data?.counts?.king,
                        },
                        {
                            id: 'matka',
                            label: 'Kalyan Matka',
                            count: data?.counts?.matka,
                        },
                    ].map((tab) => (
                        <button
                            key={tab.id}
                            type="button"
                            onClick={() => setBoard(tab.id)}
                            className={`rounded-xl px-3 py-1.5 text-xs font-semibold transition ${
                                board === tab.id
                                    ? 'bg-gold text-navy-dark'
                                    : 'bg-white/5 text-app-muted ring-1 ring-white/10 hover:bg-white/10'
                            }`}
                        >
                            {tab.label}
                            {tab.count != null ? ` (${tab.count})` : ''}
                        </button>
                    ))}
                </div>

                {!loading && latest && board === 'king' && (
                    <section className="mb-4 overflow-hidden rounded-2xl bg-[#ffe566] p-4 text-black shadow-sm ring-1 ring-black/10">
                        <p className="text-[10px] font-bold uppercase tracking-[0.2em] text-black/60">
                            Last result · King
                        </p>
                        <div className="mt-2 flex items-center gap-3">
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-base font-bold uppercase">
                                    {latest.name}
                                </p>
                            </div>
                            <div className="text-right">
                                <p className="text-[10px] font-semibold uppercase text-black/50">
                                    {yesterdayLabel}
                                </p>
                                <p className="text-2xl font-bold tabular-nums">
                                    {latest.last_result || 'XX'}
                                </p>
                            </div>
                            <div className="text-right">
                                <p className="text-[10px] font-semibold uppercase text-black/50">
                                    {todayLabel}
                                </p>
                                <p className="text-2xl font-bold tabular-nums">
                                    {latest.today_result || 'XX'}
                                </p>
                            </div>
                        </div>
                    </section>
                )}

                {!loading && latest && board === 'matka' && (
                    <section className="mb-4 overflow-hidden rounded-2xl bg-[#ffe566] p-4 text-black shadow-sm ring-1 ring-black/10">
                        <p className="text-[10px] font-bold uppercase tracking-[0.2em] text-black/60">
                            Latest · Kalyan Matka
                        </p>
                        <p className="mt-2 text-base font-bold uppercase">
                            {latest.name}
                        </p>
                        <p className="mt-1 font-display text-2xl font-semibold tracking-wide">
                            {latest.full_result || latest.display_value || 'XX'}
                        </p>
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
                    <div className="bg-[#2ebc8d] px-3 py-3 text-center text-sm font-semibold text-white sm:px-4 sm:text-base">
                        {banner ||
                            (board === 'matka'
                                ? 'Live Kalyan Matka Result'
                                : 'Satta King Fast Results')}
                    </div>

                    {board === 'king' ? (
                        <div className="flex items-center gap-2 bg-[#3a3a3a] px-3 py-2.5 text-[11px] font-semibold text-white sm:gap-3 sm:px-4 sm:text-xs">
                            <span className="w-7 sm:w-8">#</span>
                            <span className="min-w-0 flex-1">
                                Regional Offline Draw Results
                            </span>
                            <div className="flex shrink-0 items-center gap-4 pr-1 sm:gap-8 sm:pr-2">
                                <span className="w-12 text-center sm:w-14">
                                    {yesterdayLabel}
                                </span>
                                <span className="w-12 text-center sm:w-14">
                                    {todayLabel}
                                </span>
                            </div>
                        </div>
                    ) : (
                        <div className="flex items-center gap-2 bg-[#3a3a3a] px-3 py-2.5 text-[11px] font-semibold text-white sm:px-4 sm:text-xs">
                            <span className="w-7 sm:w-8">#</span>
                            <span className="min-w-0 flex-1">
                                Live Matka Result · Open / Jodi / Close
                            </span>
                        </div>
                    )}

                    {loading
                        ? [1, 2, 3, 4, 5].map((i) => (
                              <div
                                  key={i}
                                  className="h-16 animate-pulse border-b border-black/5 bg-neutral-100"
                              />
                          ))
                        : rows.map((result, index) =>
                              board === 'matka' ? (
                                  <MatkaRow
                                      key={result.id}
                                      result={result}
                                      index={index}
                                  />
                              ) : (
                                  <KingRow
                                      key={result.id}
                                      result={result}
                                      index={index}
                                      yesterdayLabel={yesterdayLabel}
                                      todayLabel={todayLabel}
                                  />
                              ),
                          )}

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
                        King {data.counts.king || 0} · Matka{' '}
                        {data.counts.matka || 0} · Showing {rows.length}
                    </p>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

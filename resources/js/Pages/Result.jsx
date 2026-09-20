import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import { formatDateTime } from '@/lib/format';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { useCallback, useEffect, useState } from 'react';

function Cell({ label, value }) {
    return (
        <div className="rounded-xl bg-navy-dark/60 px-2 py-2 text-center ring-1 ring-white/10">
            <p className="text-[10px] uppercase tracking-wide text-app-muted">
                {label}
            </p>
            <p className="mt-1 font-display text-sm font-semibold text-gold sm:text-base">
                {value || '***'}
            </p>
        </div>
    );
}

export default function Result() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [filter, setFilter] = useState('all');

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

    const rows = (data?.results || []).filter((row) => {
        if (filter === 'declared') {
            return row.open_pana || row.jodi || row.close_pana || row.result_string;
        }
        if (filter === 'live') {
            return row.status === 'open' || row.status === 'pending';
        }
        return true;
    });

    return (
        <AuthenticatedLayout>
            <Head title="Results" />

            <div className="mx-auto max-w-6xl px-4 py-6 pb-24 sm:px-6 sm:py-8 sm:pb-8 lg:px-8">
                <PageHeader
                    eyebrow="Live board"
                    title="Results"
                    subtitle="Live market board from Satta Matka API (auto refresh 30s)."
                />

                <div className="mb-4 flex flex-wrap items-center gap-2">
                    {[
                        { id: 'all', label: 'All' },
                        { id: 'declared', label: 'Declared' },
                        { id: 'live', label: 'Live' },
                    ].map((tab) => (
                        <button
                            key={tab.id}
                            type="button"
                            onClick={() => setFilter(tab.id)}
                            className={`rounded-xl px-3 py-1.5 text-xs font-semibold transition ${
                                filter === tab.id
                                    ? 'bg-gold text-navy-dark'
                                    : 'bg-white/5 text-app-muted ring-1 ring-white/10 hover:bg-white/10'
                            }`}
                        >
                            {tab.label}
                            {tab.id === 'all' && data?.counts?.total != null
                                ? ` (${data.counts.total})`
                                : ''}
                            {tab.id === 'declared' &&
                            data?.counts?.declared != null
                                ? ` (${data.counts.declared})`
                                : ''}
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
                        <p className="text-xs font-semibold uppercase tracking-[0.2em] text-white/60">
                            Latest / featured
                        </p>
                        <h2 className="mt-2 font-display text-xl font-semibold">
                            {data.latest.name}
                        </h2>
                        <p className="mt-1 text-sm text-white/70">
                            {[
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
                        <div className="mt-4 grid grid-cols-3 gap-2">
                            <Cell label="Open" value={data.latest.open_pana} />
                            <Cell label="Jodi" value={data.latest.jodi} />
                            <Cell label="Close" value={data.latest.close_pana} />
                        </div>
                    </section>
                )}

                <div className="space-y-3">
                    {loading
                        ? [1, 2, 3, 4].map((i) => (
                              <div
                                  key={i}
                                  className="h-28 animate-pulse rounded-2xl bg-card ring-1 ring-white/10"
                              />
                          ))
                        : rows.map((result) => (
                              <article
                                  key={result.id}
                                  className="rounded-2xl bg-card p-4 shadow-sm ring-1 ring-white/10 sm:p-5"
                              >
                                  <div className="mb-3 flex items-start justify-between gap-3">
                                      <div>
                                          <p className="text-sm font-semibold text-white">
                                              {result.name}
                                          </p>
                                          <p className="mt-0.5 text-xs text-app-muted">
                                              {[
                                                  result.open_time &&
                                                  result.close_time
                                                      ? `${result.open_time} – ${result.close_time}`
                                                      : null,
                                                  result.status_label ||
                                                      result.status,
                                              ]
                                                  .filter(Boolean)
                                                  .join(' · ')}
                                          </p>
                                      </div>
                                      <p className="shrink-0 rounded-full bg-white/5 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-gold ring-1 ring-gold/20">
                                          {result.status}
                                      </p>
                                  </div>
                                  <div className="grid grid-cols-3 gap-2">
                                      <Cell
                                          label="Open"
                                          value={result.open_pana}
                                      />
                                      <Cell label="Jodi" value={result.jodi} />
                                      <Cell
                                          label="Close"
                                          value={result.close_pana}
                                      />
                                  </div>
                              </article>
                          ))}

                    {!loading && rows.length === 0 && !error && (
                        <p className="rounded-2xl bg-card px-4 py-8 text-center text-sm text-app-muted ring-1 ring-white/10">
                            No markets in this filter yet.
                        </p>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

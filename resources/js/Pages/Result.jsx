import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import { formatDateTime } from '@/lib/format';
import useApiData from '@/hooks/useApiData';
import { Head } from '@inertiajs/react';

function ResultDigits({ values, size = 'md' }) {
    const box =
        size === 'lg'
            ? 'min-w-10 h-10 px-2 text-sm'
            : 'min-w-8 h-8 px-1.5 text-xs';

    if (!values?.length) {
        return (
            <span className="text-sm text-app-muted">Awaiting result…</span>
        );
    }

    return (
        <div className="flex flex-wrap items-center gap-2">
            {values.map((n, index) => (
                <span
                    key={`${n}-${index}`}
                    className={`inline-flex items-center justify-center rounded-full bg-gold font-semibold text-navy-dark ${box}`}
                >
                    {n}
                </span>
            ))}
        </div>
    );
}

function ResultMeta({ result }) {
    const bits = [
        result.open_time && result.close_time
            ? `${result.open_time} – ${result.close_time}`
            : null,
        result.status_label || result.status,
        result.drawn_at ? formatDateTime(result.drawn_at) : null,
    ].filter(Boolean);

    return (
        <p className="mt-1 text-xs text-app-muted sm:text-sm text-white/70">
            {bits.join(' · ')}
        </p>
    );
}

export default function Result() {
    const { data, loading, error } = useApiData('api.results');

    return (
        <AuthenticatedLayout>
            <Head title="Results" />

            <div className="mx-auto max-w-6xl px-4 py-6 pb-24 sm:px-6 sm:py-8 sm:pb-8 lg:px-8">
                <PageHeader
                    eyebrow="Live board"
                    title="Results"
                    subtitle="Live market results from Satta Matka API — jo API bhejegi, wahi dikhega."
                />

                {error && (
                    <div className="mb-6 rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
                        {error}
                    </div>
                )}

                {!loading && data?.latest && (
                    <section className="mb-6 overflow-hidden rounded-2xl bg-card p-5 text-white shadow-sm ring-1 ring-gold/25 sm:p-6">
                        <p className="text-xs font-semibold uppercase tracking-[0.2em] text-white/60">
                            Latest result
                        </p>
                        <h2 className="mt-2 font-display text-xl font-semibold">
                            {data.latest.name}
                        </h2>
                        <ResultMeta result={data.latest} />
                        <div className="mt-4">
                            <ResultDigits
                                values={
                                    data.latest.numbers?.length
                                        ? data.latest.numbers
                                        : data.latest.result_string
                                          ? [data.latest.result_string]
                                          : []
                                }
                                size="lg"
                            />
                        </div>
                        {(data.latest.open_pana ||
                            data.latest.jodi ||
                            data.latest.close_pana) && (
                            <div className="mt-4 grid grid-cols-3 gap-2 text-center text-xs sm:text-sm">
                                <div className="rounded-xl bg-white/5 px-2 py-2 ring-1 ring-white/10">
                                    <p className="text-white/50">Open</p>
                                    <p className="mt-1 font-semibold text-gold">
                                        {data.latest.open_pana || '—'}
                                    </p>
                                </div>
                                <div className="rounded-xl bg-white/5 px-2 py-2 ring-1 ring-white/10">
                                    <p className="text-white/50">Jodi</p>
                                    <p className="mt-1 font-semibold text-gold">
                                        {data.latest.jodi || '—'}
                                    </p>
                                </div>
                                <div className="rounded-xl bg-white/5 px-2 py-2 ring-1 ring-white/10">
                                    <p className="text-white/50">Close</p>
                                    <p className="mt-1 font-semibold text-gold">
                                        {data.latest.close_pana || '—'}
                                    </p>
                                </div>
                            </div>
                        )}
                    </section>
                )}

                <div className="space-y-3">
                    {loading
                        ? [1, 2, 3].map((i) => (
                              <div
                                  key={i}
                                  className="h-28 animate-pulse rounded-2xl bg-card ring-1 ring-white/10"
                              />
                          ))
                        : data?.results?.map((result) => (
                              <article
                                  key={result.id}
                                  className="rounded-2xl bg-card p-4 shadow-sm ring-1 ring-white/10 sm:p-5"
                              >
                                  <div className="flex items-start justify-between gap-3">
                                      <div>
                                          <p className="text-sm font-semibold text-white">
                                              {result.name}
                                          </p>
                                          <ResultMeta result={result} />
                                      </div>
                                      <p className="shrink-0 rounded-full bg-white/5 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-gold ring-1 ring-gold/20">
                                          {result.status}
                                      </p>
                                  </div>
                                  <div className="mt-3">
                                      <ResultDigits
                                          values={
                                              result.numbers?.length
                                                  ? result.numbers
                                                  : result.result_string
                                                    ? [result.result_string]
                                                    : []
                                          }
                                      />
                                  </div>
                              </article>
                          ))}

                    {!loading && !data?.results?.length && !error && (
                        <p className="rounded-2xl bg-card px-4 py-8 text-center text-sm text-app-muted ring-1 ring-white/10">
                            No results from API yet.
                        </p>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

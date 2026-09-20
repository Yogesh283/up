import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import { formatDateTime } from '@/lib/format';
import useApiData from '@/hooks/useApiData';
import { Head } from '@inertiajs/react';

export default function Result() {
    const { data, loading, error } = useApiData('api.results');

    return (
        <AuthenticatedLayout>
            <Head title="Results" />

            <div className="mx-auto max-w-6xl px-4 py-6 pb-24 sm:px-6 sm:py-8 sm:pb-8 lg:px-8">
                <PageHeader
                    eyebrow="Draws"
                    title="Results"
                    subtitle="Latest winning numbers from completed draws."
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
                        <p className="mt-1 text-sm text-white/70">
                            {formatDateTime(data.latest.drawn_at)} · Prize{' '}
                            {data.latest.prize}
                        </p>
                        <div className="mt-4 flex flex-wrap gap-2">
                            {data.latest.numbers?.map((n) => (
                                <span
                                    key={`latest-${n}`}
                                    className="flex h-10 w-10 items-center justify-center rounded-full bg-gold text-navy-dark text-sm font-semibold"
                                >
                                    {n}
                                </span>
                            ))}
                        </div>
                        <p className="mt-4 text-xs text-white/60">
                            {data.latest.winners} winners
                        </p>
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
                                          <p className="mt-0.5 text-xs text-app-muted">
                                              {formatDateTime(result.drawn_at)}
                                          </p>
                                      </div>
                                      <p className="text-sm font-semibold text-gold">
                                          {result.prize}
                                      </p>
                                  </div>
                                  <div className="mt-3 flex flex-wrap gap-2">
                                      {result.numbers?.map((n) => (
                                          <span
                                              key={`${result.id}-${n}`}
                                              className="flex h-8 w-8 items-center justify-center rounded-full bg-gold text-xs font-semibold text-navy-dark"
                                          >
                                              {n}
                                          </span>
                                      ))}
                                  </div>
                              </article>
                          ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

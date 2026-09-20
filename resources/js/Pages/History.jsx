import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import StatusBadge from '@/Components/StatusBadge';
import { formatDateTime, formatMoney } from '@/lib/format';
import useApiData from '@/hooks/useApiData';
import { Head } from '@inertiajs/react';

export default function History() {
    const { data, loading, error } = useApiData('api.history');

    return (
        <AuthenticatedLayout>
            <Head title="History" />

            <div className="mx-auto max-w-6xl px-4 py-6 pb-24 sm:px-6 sm:py-8 sm:pb-8 lg:px-8">
                <PageHeader
                    eyebrow="Account"
                    title="History"
                    subtitle="Your ticket play history and outcomes."
                />

                {error && (
                    <div className="mb-6 rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
                        {error}
                    </div>
                )}

                <div className="mb-6 grid grid-cols-3 gap-3">
                    {[
                        {
                            label: 'Tickets',
                            value: loading ? '—' : data?.summary?.total_tickets,
                        },
                        {
                            label: 'Spent',
                            value: loading
                                ? '—'
                                : formatMoney(data?.summary?.total_spent),
                        },
                        {
                            label: 'Won',
                            value: loading
                                ? '—'
                                : formatMoney(data?.summary?.total_won),
                        },
                    ].map((item) => (
                        <div
                            key={item.label}
                            className="rounded-2xl bg-card p-4 shadow-sm ring-1 ring-white/10"
                        >
                            <p className="text-[11px] text-app-muted">{item.label}</p>
                            <p className="mt-1 font-display text-lg font-semibold text-gold">
                                {item.value}
                            </p>
                        </div>
                    ))}
                </div>

                <div className="space-y-3">
                    {loading
                        ? [1, 2, 3].map((i) => (
                              <div
                                  key={i}
                                  className="h-24 animate-pulse rounded-2xl bg-card ring-1 ring-white/10"
                              />
                          ))
                        : data?.items?.map((item) => (
                              <article
                                  key={item.id}
                                  className="rounded-2xl bg-card p-4 shadow-sm ring-1 ring-white/10"
                              >
                                  <div className="flex items-start justify-between gap-3">
                                      <div>
                                          <p className="text-sm font-semibold text-white">
                                              {item.draw}
                                          </p>
                                          <p className="mt-0.5 text-xs text-app-muted">
                                              {item.id} · {formatDateTime(item.created_at)}
                                          </p>
                                      </div>
                                      <StatusBadge status={item.status} />
                                  </div>
                                  <div className="mt-3 flex flex-wrap gap-1.5">
                                      {item.numbers?.map((n) => (
                                          <span
                                              key={`${item.id}-${n}`}
                                              className="flex h-7 w-7 items-center justify-center rounded-full bg-gold text-[11px] font-semibold text-navy-dark"
                                          >
                                              {n}
                                          </span>
                                      ))}
                                  </div>
                                  <div className="mt-3 flex justify-between text-xs text-app-muted">
                                      <span>Played {formatMoney(item.amount)}</span>
                                      <span className="font-medium text-gold">
                                          {item.prize > 0
                                              ? `Won ${formatMoney(item.prize)}`
                                              : 'No win'}
                                      </span>
                                  </div>
                              </article>
                          ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

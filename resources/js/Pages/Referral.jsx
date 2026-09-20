import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import StatusBadge from '@/Components/StatusBadge';
import { formatDateTime, formatMoney } from '@/lib/format';
import useApiData from '@/hooks/useApiData';
import { Head } from '@inertiajs/react';
import { useState } from 'react';

export default function Referral() {
    const { data, loading, error } = useApiData('api.referral');
    const [copied, setCopied] = useState(false);

    const copyCode = async () => {
        if (!data?.code) return;
        try {
            await navigator.clipboard.writeText(data.share_link || data.code);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            setCopied(false);
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Referral" />

            <div className="mx-auto max-w-6xl px-4 py-6 pb-24 sm:px-6 sm:py-8 sm:pb-8 lg:px-8">
                <PageHeader
                    eyebrow="Invite"
                    title="Referral"
                    subtitle="Invite friends and earn rewards when they join."
                />

                {error && (
                    <div className="mb-6 rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
                        {error}
                    </div>
                )}

                <section className="mb-6 rounded-2xl bg-card p-5 ring-1 ring-gold/30 text-white sm:p-6">
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-white/60">
                        Your code
                    </p>
                    <p className="mt-2 font-display text-3xl font-semibold tracking-wide">
                        {loading ? '········' : data?.code}
                    </p>
                    <p className="mt-2 break-all text-xs text-white/65">
                        {loading ? '—' : data?.share_link}
                    </p>
                    <button
                        type="button"
                        onClick={copyCode}
                        disabled={loading}
                        className="mt-4 rounded-xl bg-gold px-4 py-2.5 text-sm font-semibold text-navy-dark transition hover:bg-gold-bright disabled:opacity-50"
                    >
                        {copied ? 'Copied!' : 'Copy invite link'}
                    </button>
                </section>

                <div className="mb-6 grid grid-cols-3 gap-3">
                    {[
                        { label: 'Invited', value: data?.stats?.invited },
                        { label: 'Joined', value: data?.stats?.joined },
                        {
                            label: 'Earned',
                            value: loading
                                ? '—'
                                : formatMoney(data?.stats?.earned),
                        },
                    ].map((item) => (
                        <div
                            key={item.label}
                            className="rounded-2xl bg-card p-4 shadow-sm ring-1 ring-white/10"
                        >
                            <p className="text-[11px] text-app-muted">{item.label}</p>
                            <p className="mt-1 font-display text-lg font-semibold text-gold">
                                {loading && item.label !== 'Earned' ? '—' : item.value}
                            </p>
                        </div>
                    ))}
                </div>

                <section className="rounded-2xl bg-card p-4 shadow-sm ring-1 ring-white/10 sm:p-5">
                    <h2 className="mb-4 font-display text-lg font-semibold text-gold">
                        Your referrals
                    </h2>
                    <ul className="space-y-3">
                        {loading
                            ? [1, 2, 3].map((i) => (
                                  <li
                                      key={i}
                                      className="h-14 animate-pulse rounded-xl bg-navy-dark"
                                  />
                              ))
                            : data?.referrals?.map((item, index) => (
                                  <li
                                      key={`${item.mobile}-${index}`}
                                      className="flex items-center justify-between gap-3 rounded-xl border border-white/10 px-3 py-3"
                                  >
                                      <div>
                                          <p className="text-sm font-semibold text-white">
                                              {item.name}
                                          </p>
                                          <p className="text-xs text-app-muted">
                                              {item.mobile} ·{' '}
                                              {formatDateTime(item.joined_at)}
                                          </p>
                                      </div>
                                      <div className="text-right">
                                          <StatusBadge status={item.status} />
                                          <p className="mt-1 text-xs text-gold">
                                              {item.reward > 0
                                                  ? formatMoney(item.reward)
                                                  : '—'}
                                          </p>
                                      </div>
                                  </li>
                              ))}
                    </ul>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}

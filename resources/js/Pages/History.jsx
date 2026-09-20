import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BetCard from '@/Components/BetCard';
import PageHeader from '@/Components/PageHeader';
import { formatMoney } from '@/lib/format';
import useApiData from '@/hooks/useApiData';
import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const TABS = [
    { id: 'all', label: 'All' },
    { id: 'active', label: 'Running' },
    { id: 'won', label: 'Won' },
    { id: 'lost', label: 'Lost' },
];

export default function History() {
    const { data, loading, error } = useApiData('api.history');
    const [tab, setTab] = useState('all');

    const items = useMemo(() => {
        if (!data) return [];
        if (tab === 'active') return data.active || [];
        if (tab === 'won') return data.won || [];
        if (tab === 'lost') return data.lost || [];
        return data.items || [];
    }, [data, tab]);

    return (
        <AuthenticatedLayout>
            <Head title="History" />

            <div className="mx-auto max-w-6xl px-4 py-6 pb-24 sm:px-6 sm:py-8 sm:pb-8 lg:px-8">
                <PageHeader
                    eyebrow="Account"
                    title="Bet history"
                    subtitle="Running, won aur lost — clear status ke sath."
                />

                {error && (
                    <div className="mb-6 rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
                        {error}
                    </div>
                )}

                <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {[
                        {
                            label: 'Total bets',
                            value: loading ? '—' : data?.summary?.total_tickets,
                        },
                        {
                            label: 'Running',
                            value: loading ? '—' : data?.summary?.active,
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

                <div className="mb-4 flex flex-wrap gap-2">
                    {TABS.map((t) => (
                        <button
                            key={t.id}
                            type="button"
                            onClick={() => setTab(t.id)}
                            className={`rounded-xl px-3 py-1.5 text-xs font-semibold transition ${
                                tab === t.id
                                    ? 'bg-gold text-navy-dark'
                                    : 'bg-white/5 text-app-muted ring-1 ring-white/10'
                            }`}
                        >
                            {t.label}
                            {!loading && t.id === 'active'
                                ? ` (${data?.summary?.active || 0})`
                                : ''}
                        </button>
                    ))}
                </div>

                <ul className="space-y-3">
                    {loading
                        ? [1, 2, 3].map((i) => (
                              <li
                                  key={i}
                                  className="h-28 animate-pulse rounded-2xl bg-card ring-1 ring-white/10"
                              />
                          ))
                        : items.map((bet) => <BetCard key={bet.id} bet={bet} />)}
                </ul>

                {!loading && items.length === 0 && (
                    <p className="rounded-xl border border-dashed border-white/10 px-4 py-8 text-center text-sm text-app-muted">
                        Is status me koi bet nahi mili.
                    </p>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

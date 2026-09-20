import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import StatusBadge from '@/Components/StatusBadge';
import { formatDateTime, formatMoney } from '@/lib/format';
import { Head, usePage } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useMemo, useState } from 'react';

function formatDrawTime(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString(undefined, {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function StatSkeleton() {
    return (
        <div className="animate-pulse rounded-2xl bg-card p-4 shadow-sm ring-1 ring-white/10">
            <div className="h-3 w-20 rounded bg-blue/50" />
            <div className="mt-3 h-7 w-16 rounded bg-blue/50" />
            <div className="mt-2 h-3 w-24 rounded bg-navy-dark" />
        </div>
    );
}

export default function Dashboard() {
    const { auth } = usePage().props;
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const [selectedDrawId, setSelectedDrawId] = useState(null);
    const [selectedNumbers, setSelectedNumbers] = useState([]);
    const [placing, setPlacing] = useState(false);
    const [betMessage, setBetMessage] = useState(null);
    const [betError, setBetError] = useState(null);

    const loadDashboard = () => {
        setLoading(true);
        return axios
            .get(route('api.dashboard'))
            .then((response) => {
                setData(response.data);
                setError(null);
                if (!selectedDrawId && response.data.upcoming_draws?.length) {
                    setSelectedDrawId(response.data.upcoming_draws[0].id);
                }
            })
            .catch(() => {
                setError('Could not load dashboard data. Try again.');
            })
            .finally(() => {
                setLoading(false);
            });
    };

    useEffect(() => {
        loadDashboard();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const selectedDraw = useMemo(
        () => data?.upcoming_draws?.find((d) => d.id === selectedDrawId) || null,
        [data, selectedDrawId],
    );

    const pickCount = selectedDraw?.pick_count || 6;
    const maxNumber = selectedDraw?.max_number || 49;
    const ticketPrice = selectedDraw?.ticket_price_value || 0;

    const numberPool = useMemo(
        () => Array.from({ length: maxNumber }, (_, i) => i + 1),
        [maxNumber],
    );

    const toggleNumber = (n) => {
        setBetMessage(null);
        setBetError(null);
        setSelectedNumbers((prev) => {
            if (prev.includes(n)) {
                return prev.filter((x) => x !== n);
            }
            if (prev.length >= pickCount) {
                return prev;
            }
            return [...prev, n].sort((a, b) => a - b);
        });
    };

    const quickPick = () => {
        setBetMessage(null);
        setBetError(null);
        const pool = [...numberPool];
        const picks = [];
        while (picks.length < pickCount && pool.length) {
            const idx = Math.floor(Math.random() * pool.length);
            picks.push(pool.splice(idx, 1)[0]);
        }
        setSelectedNumbers(picks.sort((a, b) => a - b));
    };

    const clearNumbers = () => {
        setSelectedNumbers([]);
        setBetMessage(null);
        setBetError(null);
    };

    const placeBet = async () => {
        if (!selectedDraw) {
            setBetError('Please select a draw.');
            return;
        }
        if (selectedNumbers.length !== pickCount) {
            setBetError(`Select exactly ${pickCount} numbers.`);
            return;
        }

        setPlacing(true);
        setBetError(null);
        setBetMessage(null);

        try {
            const response = await axios.post(route('api.bets.store'), {
                draw_id: selectedDraw.id,
                numbers: selectedNumbers,
            });
            setBetMessage(response.data.message);
            setSelectedNumbers([]);
            await loadDashboard();
        } catch (err) {
            const errors = err.response?.data?.errors;
            setBetError(
                errors?.numbers?.[0] ||
                    errors?.amount?.[0] ||
                    errors?.draw_id?.[0] ||
                    err.response?.data?.message ||
                    'Could not place bet. Try again.',
            );
        } finally {
            setPlacing(false);
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />

            <div className="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                <div className="dash-fade mb-6 sm:mb-8">
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-gold/70">
                        Overview
                    </p>
                    <h1 className="mt-1 font-display text-2xl font-semibold tracking-tight text-white sm:text-3xl">
                        Hi, {auth.user?.name?.split(' ')[0] || 'Player'}
                    </h1>
                    <p className="mt-1 text-sm text-app-muted">
                        Select a draw, pick your numbers, and place your bet.
                    </p>
                </div>

                {(error || betError) && (
                    <div className="mb-6 rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
                        {betError || error}
                    </div>
                )}
                {betMessage && (
                    <div className="mb-6 rounded-xl border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">
                        {betMessage}
                    </div>
                )}

                <section className="mb-6 grid grid-cols-2 gap-3 sm:mb-8 sm:gap-4 lg:grid-cols-4">
                    {loading
                        ? Array.from({ length: 4 }).map((_, i) => (
                              <StatSkeleton key={i} />
                          ))
                        : data?.stats?.map((stat) => (
                              <div
                                  key={stat.key}
                                  className="dash-fade rounded-2xl bg-card p-4 shadow-sm ring-1 ring-white/10"
                              >
                                  <p className="text-xs font-medium text-app-muted">
                                      {stat.label}
                                  </p>
                                  <p className="mt-2 font-display text-2xl font-semibold tracking-tight text-gold">
                                      {stat.display}
                                  </p>
                                  <p className="mt-1 text-[11px] text-app-muted">
                                      {stat.hint}
                                  </p>
                              </div>
                          ))}
                </section>

                <section className="dash-fade mb-6 rounded-2xl bg-card p-5 shadow-sm ring-1 ring-gold/20 sm:p-6">
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 className="font-display text-lg font-semibold text-gold">
                                Place a bet
                            </h2>
                            <p className="mt-0.5 text-xs text-app-muted">
                                Choose draw · pick {pickCount} numbers · confirm
                            </p>
                        </div>
                        <p className="text-sm text-app-text">
                            Ticket:{' '}
                            <span className="font-semibold text-gold">
                                {selectedDraw
                                    ? selectedDraw.ticket_price
                                    : '—'}
                            </span>
                        </p>
                    </div>

                    <div className="mb-4">
                        <p className="mb-2 text-xs font-medium uppercase tracking-wide text-app-muted">
                            Select draw
                        </p>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {(data?.upcoming_draws || []).map((draw) => {
                                const active = selectedDrawId === draw.id;
                                return (
                                    <button
                                        key={draw.id}
                                        type="button"
                                        onClick={() => {
                                            setSelectedDrawId(draw.id);
                                            setSelectedNumbers([]);
                                            setBetError(null);
                                            setBetMessage(null);
                                        }}
                                        className={`rounded-xl border px-4 py-3 text-left transition ${
                                            active
                                                ? 'border-gold bg-gold/10'
                                                : 'border-white/10 bg-navy-dark/40 hover:border-white/20'
                                        }`}
                                    >
                                        <p className="text-sm font-semibold text-white">
                                            {draw.name}
                                        </p>
                                        <p className="mt-0.5 text-xs text-app-muted">
                                            {formatDrawTime(draw.draw_at)} ·{' '}
                                            {draw.ticket_price} · Prize{' '}
                                            {draw.prize}
                                        </p>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <p className="text-xs font-medium uppercase tracking-wide text-app-muted">
                            Your numbers ({selectedNumbers.length}/{pickCount})
                        </p>
                        <div className="flex gap-2">
                            <button
                                type="button"
                                onClick={quickPick}
                                className="rounded-lg bg-blue px-3 py-1.5 text-xs font-medium text-app-text transition hover:bg-blue/80"
                            >
                                Quick pick
                            </button>
                            <button
                                type="button"
                                onClick={clearNumbers}
                                className="rounded-lg bg-white/5 px-3 py-1.5 text-xs font-medium text-app-muted transition hover:bg-white/10"
                            >
                                Clear
                            </button>
                        </div>
                    </div>

                    {selectedNumbers.length > 0 && (
                        <div className="mb-3 flex flex-wrap gap-2">
                            {selectedNumbers.map((n) => (
                                <span
                                    key={`sel-${n}`}
                                    className="flex h-8 w-8 items-center justify-center rounded-full bg-gold text-xs font-bold text-navy-dark"
                                >
                                    {n}
                                </span>
                            ))}
                        </div>
                    )}

                    <div className="mb-4 grid grid-cols-7 gap-1.5 sm:grid-cols-10">
                        {numberPool.map((n) => {
                            const active = selectedNumbers.includes(n);
                            const full =
                                !active && selectedNumbers.length >= pickCount;
                            return (
                                <button
                                    key={n}
                                    type="button"
                                    disabled={full}
                                    onClick={() => toggleNumber(n)}
                                    className={`flex h-9 items-center justify-center rounded-lg text-xs font-semibold transition sm:h-10 sm:text-sm ${
                                        active
                                            ? 'bg-gold text-navy-dark'
                                            : full
                                              ? 'cursor-not-allowed bg-white/5 text-app-muted/40'
                                              : 'bg-navy-dark text-app-text hover:bg-blue'
                                    }`}
                                >
                                    {n}
                                </button>
                            );
                        })}
                    </div>

                    <button
                        type="button"
                        onClick={placeBet}
                        disabled={
                            placing ||
                            loading ||
                            !selectedDraw ||
                            selectedNumbers.length !== pickCount
                        }
                        className="auth-btn"
                    >
                        {placing
                            ? 'Placing bet…'
                            : `Place bet · ${formatMoney(ticketPrice)}`}
                    </button>
                </section>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="dash-fade rounded-2xl bg-card p-5 shadow-sm ring-1 ring-white/10 sm:p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="font-display text-lg font-semibold text-gold">
                                My recent bets
                            </h2>
                        </div>

                        {loading ? (
                            <div className="space-y-3">
                                {[1, 2].map((i) => (
                                    <div
                                        key={i}
                                        className="h-20 animate-pulse rounded-xl bg-navy-dark"
                                    />
                                ))}
                            </div>
                        ) : (
                            <ul className="space-y-3">
                                {data?.my_bets?.map((bet) => (
                                    <li
                                        key={bet.id}
                                        className="rounded-xl border border-white/10 bg-blue/30 px-4 py-3"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="text-sm font-semibold text-white">
                                                    {bet.draw_name}
                                                </p>
                                                <p className="mt-0.5 text-xs text-app-muted">
                                                    {formatDateTime(
                                                        bet.created_at,
                                                    )}{' '}
                                                    · {formatMoney(bet.amount)}
                                                </p>
                                            </div>
                                            <StatusBadge status={bet.status} />
                                        </div>
                                        <div className="mt-2 flex flex-wrap gap-1.5">
                                            {bet.numbers?.map((n) => (
                                                <span
                                                    key={`${bet.id}-${n}`}
                                                    className="flex h-7 w-7 items-center justify-center rounded-full bg-gold text-[11px] font-semibold text-navy-dark"
                                                >
                                                    {n}
                                                </span>
                                            ))}
                                        </div>
                                    </li>
                                ))}
                                {!data?.my_bets?.length && (
                                    <p className="text-sm text-app-muted">
                                        No bets yet. Place your first bet above.
                                    </p>
                                )}
                            </ul>
                        )}
                    </section>

                    <section className="dash-fade rounded-2xl bg-card p-5 shadow-sm ring-1 ring-white/10 sm:p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="font-display text-lg font-semibold text-gold">
                                Recent results
                            </h2>
                        </div>

                        {loading ? (
                            <div className="space-y-3">
                                {[1, 2].map((i) => (
                                    <div
                                        key={i}
                                        className="h-24 animate-pulse rounded-xl bg-navy-dark"
                                    />
                                ))}
                            </div>
                        ) : (
                            <ul className="space-y-4">
                                {data?.recent_results?.map((result) => (
                                    <li key={result.id}>
                                        <div className="mb-2 flex items-center justify-between gap-2">
                                            <div>
                                                <p className="text-sm font-semibold text-white">
                                                    {result.name}
                                                </p>
                                                <p className="text-xs text-app-muted">
                                                    {formatDrawTime(
                                                        result.drawn_at,
                                                    )}
                                                </p>
                                            </div>
                                            <p className="text-xs font-medium uppercase tracking-wide text-gold">
                                                {result.status ||
                                                    result.prize ||
                                                    '—'}
                                            </p>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            {(result.numbers?.length
                                                ? result.numbers
                                                : result.result_string
                                                  ? [result.result_string]
                                                  : []
                                            ).map((n, index) => (
                                                <span
                                                    key={`${result.id}-${n}-${index}`}
                                                    className="inline-flex h-8 min-w-8 items-center justify-center rounded-full bg-gold px-2 text-xs font-semibold text-navy-dark"
                                                >
                                                    {n}
                                                </span>
                                            ))}
                                        </div>
                                    </li>
                                ))}
                                {!data?.recent_results?.length && (
                                    <li className="text-sm text-app-muted">
                                        No live results yet.
                                    </li>
                                )}
                            </ul>
                        )}
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

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

function pad2(n) {
    return String(n).padStart(2, '0');
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

    const [board, setBoard] = useState('king');
    const [marketSearch, setMarketSearch] = useState('');
    const [selectedDrawId, setSelectedDrawId] = useState(null);
    const [selectedNumbers, setSelectedNumbers] = useState([]);
    const [betAmount, setBetAmount] = useState('');
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

                const draws = response.data.upcoming_draws || [];
                const ticket = response.data.betting?.ticket_price ?? 10;
                setBetAmount((prev) => (prev === '' ? String(ticket) : prev));

                setSelectedDrawId((prev) => {
                    if (prev && draws.some((d) => d.id === prev)) {
                        return prev;
                    }
                    const preferred =
                        draws.find((d) => d.board === board && d.status === 'open') ||
                        draws.find((d) => d.status === 'open') ||
                        draws[0];
                    return preferred?.id ?? null;
                });
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

    const betting = data?.betting || {};
    const pickCount = betting.pick_count || 1;
    const minNumber = betting.min_number ?? 0;
    const maxNumber = betting.max_number ?? 99;
    const defaultTicket = betting.ticket_price ?? 10;
    const minAmount = betting.min_amount ?? defaultTicket;
    const maxAmount = betting.max_amount ?? 10000;
    const prizeMultiplier = betting.prize_multiplier ?? 9;

    const boardDraws = useMemo(() => {
        const list = (data?.upcoming_draws || []).filter((d) => d.board === board);
        const q = marketSearch.trim().toLowerCase();
        if (!q) return list;
        return list.filter((d) => String(d.name || '').toLowerCase().includes(q));
    }, [data, board, marketSearch]);

    const selectedDraw = useMemo(
        () =>
            data?.upcoming_draws?.find((d) => d.id === selectedDrawId) || null,
        [data, selectedDrawId],
    );

    const amountValue = Number(betAmount) || 0;
    const potentialWin = amountValue > 0 ? amountValue * prizeMultiplier : 0;

    const numberPool = useMemo(
        () =>
            Array.from(
                { length: maxNumber - minNumber + 1 },
                (_, i) => minNumber + i,
            ),
        [minNumber, maxNumber],
    );

    const selectBoard = (next) => {
        setBoard(next);
        setMarketSearch('');
        setSelectedNumbers([]);
        setBetError(null);
        setBetMessage(null);
        const first =
            (data?.upcoming_draws || []).find(
                (d) => d.board === next && d.status === 'open',
            ) ||
            (data?.upcoming_draws || []).find((d) => d.board === next);
        setSelectedDrawId(first?.id ?? null);
    };

    const toggleNumber = (n) => {
        setBetMessage(null);
        setBetError(null);
        setSelectedNumbers((prev) => {
            if (prev.includes(n)) {
                return prev.filter((x) => x !== n);
            }
            if (pickCount === 1) {
                return [n];
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
            setBetError('Please select a market.');
            return;
        }
        if (selectedDraw.status !== 'open') {
            setBetError('This market is closed — result already declared.');
            return;
        }
        if (selectedNumbers.length !== pickCount) {
            setBetError(
                pickCount === 1
                    ? 'Select 1 number (00–99).'
                    : `Select exactly ${pickCount} numbers.`,
            );
            return;
        }
        if (amountValue < minAmount || amountValue > maxAmount) {
            setBetError(
                `Amount must be between ${formatMoney(minAmount)} and ${formatMoney(maxAmount)}.`,
            );
            return;
        }

        setPlacing(true);
        setBetError(null);
        setBetMessage(null);

        try {
            const response = await axios.post(route('api.bets.store'), {
                draw_id: selectedDraw.id,
                board: selectedDraw.board,
                numbers: selectedNumbers,
                amount: amountValue,
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
                    errors?.board?.[0] ||
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
                        Kisi bhi number pe jitni marzi amount — jeet pe 1₹ = 9₹
                        wallet me.
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
                                Board → market → number (00–99) → amount
                            </p>
                        </div>
                        <p className="text-sm text-app-text">
                            Win rate 1₹ → ₹{prizeMultiplier}:{' '}
                            <span className="font-semibold text-gold">
                                {potentialWin
                                    ? formatMoney(potentialWin)
                                    : '—'}
                            </span>
                        </p>
                    </div>

                    <div className="mb-4 flex gap-2">
                        {[
                            { id: 'king', label: 'Satta King' },
                            { id: 'matka', label: 'Kalyan Matka' },
                        ].map((tab) => {
                            const active = board === tab.id;
                            return (
                                <button
                                    key={tab.id}
                                    type="button"
                                    onClick={() => selectBoard(tab.id)}
                                    className={`rounded-xl px-4 py-2 text-sm font-semibold transition ${
                                        active
                                            ? 'bg-gold text-navy-dark'
                                            : 'bg-navy-dark/60 text-app-muted ring-1 ring-white/10 hover:text-white'
                                    }`}
                                >
                                    {tab.label}
                                </button>
                            );
                        })}
                    </div>

                    <div className="mb-3">
                        <input
                            type="search"
                            value={marketSearch}
                            onChange={(e) => setMarketSearch(e.target.value)}
                            placeholder="Search market name…"
                            className="w-full rounded-xl border border-white/10 bg-navy-dark/50 px-4 py-2.5 text-sm text-white placeholder:text-app-muted focus:border-gold/40 focus:outline-none"
                        />
                    </div>

                    <div className="mb-4 max-h-56 space-y-2 overflow-y-auto pr-1">
                        {boardDraws.map((draw) => {
                            const active = selectedDrawId === draw.id;
                            const closed = draw.status !== 'open';
                            return (
                                <button
                                    key={draw.id}
                                    type="button"
                                    disabled={closed}
                                    onClick={() => {
                                        setSelectedDrawId(draw.id);
                                        setSelectedNumbers([]);
                                        setBetError(null);
                                        setBetMessage(null);
                                    }}
                                    className={`flex w-full items-center justify-between gap-3 rounded-xl border px-4 py-3 text-left transition ${
                                        active
                                            ? 'border-gold bg-gold/10'
                                            : closed
                                              ? 'cursor-not-allowed border-white/5 bg-navy-dark/20 opacity-50'
                                              : 'border-white/10 bg-navy-dark/40 hover:border-white/20'
                                    }`}
                                >
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold text-white">
                                            {draw.name}
                                        </p>
                                        <p className="mt-0.5 text-xs text-app-muted">
                                            {draw.time_label ||
                                                formatDrawTime(draw.draw_at)}{' '}
                                            · {draw.ticket_price} · Prize{' '}
                                            {draw.prize}
                                        </p>
                                    </div>
                                    <span
                                        className={`shrink-0 rounded-md px-2 py-1 text-[10px] font-bold uppercase tracking-wide ${
                                            closed
                                                ? 'bg-white/10 text-app-muted'
                                                : 'bg-success/20 text-success'
                                        }`}
                                    >
                                        {closed
                                            ? draw.current_result || 'Closed'
                                            : 'Open'}
                                    </span>
                                </button>
                            );
                        })}
                        {!loading && boardDraws.length === 0 && (
                            <p className="py-4 text-center text-sm text-app-muted">
                                {marketSearch
                                    ? 'No market matched your search.'
                                    : 'No markets loaded yet. Try Refresh.'}
                            </p>
                        )}
                    </div>

                    <div className="mb-4 grid gap-3 sm:grid-cols-2">
                        <label className="block">
                            <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-app-muted">
                                Bet amount (₹)
                            </span>
                            <input
                                type="number"
                                min={minAmount}
                                max={maxAmount}
                                step="1"
                                value={betAmount}
                                onChange={(e) => setBetAmount(e.target.value)}
                                className="w-full rounded-xl border border-white/10 bg-navy-dark/50 px-4 py-2.5 text-sm text-white focus:border-gold/40 focus:outline-none"
                            />
                        </label>
                        <div className="flex items-end">
                            <p className="w-full rounded-xl border border-white/10 bg-navy-dark/30 px-4 py-2.5 text-sm text-app-muted">
                                Selected:{' '}
                                <span className="font-semibold text-white">
                                    {selectedDraw?.name || '—'}
                                </span>
                            </p>
                        </div>
                    </div>

                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <p className="text-xs font-medium uppercase tracking-wide text-app-muted">
                            Your number ({selectedNumbers.length}/{pickCount})
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
                                    className="flex h-9 min-w-9 items-center justify-center rounded-full bg-gold px-2 text-sm font-bold text-navy-dark"
                                >
                                    {pad2(n)}
                                </span>
                            ))}
                        </div>
                    )}

                    <div className="mb-4 grid grid-cols-10 gap-1.5">
                        {numberPool.map((n) => {
                            const active = selectedNumbers.includes(n);
                            const full =
                                pickCount > 1 &&
                                !active &&
                                selectedNumbers.length >= pickCount;
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
                                    {pad2(n)}
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
                            selectedDraw.status !== 'open' ||
                            selectedNumbers.length !== pickCount
                        }
                        className="auth-btn"
                    >
                        {placing
                            ? 'Placing bet…'
                            : `Place bet · ${formatMoney(amountValue || defaultTicket)}`}
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
                                                    {bet.status === 'won' &&
                                                        bet.prize > 0 && (
                                                            <>
                                                                {' '}
                                                                · Won{' '}
                                                                {formatMoney(
                                                                    bet.prize,
                                                                )}
                                                            </>
                                                        )}
                                                    {bet.result_value && (
                                                        <>
                                                            {' '}
                                                            · Result{' '}
                                                            {bet.result_value}
                                                        </>
                                                    )}
                                                </p>
                                            </div>
                                            <StatusBadge status={bet.status} />
                                        </div>
                                        <div className="mt-2 flex flex-wrap gap-1.5">
                                            {(
                                                bet.numbers_display ||
                                                bet.numbers ||
                                                []
                                            ).map((n) => (
                                                <span
                                                    key={`${bet.id}-${n}`}
                                                    className="flex h-7 min-w-7 items-center justify-center rounded-full bg-gold px-1.5 text-[11px] font-semibold text-navy-dark"
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
                                                  : result.today_result &&
                                                      result.today_result !==
                                                          'XX'
                                                    ? [result.today_result]
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

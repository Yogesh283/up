import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BetCard from '@/Components/BetCard';
import { formatMoney } from '@/lib/format';
import { Head, usePage } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useMemo, useRef, useState } from 'react';

function formatDrawTime(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString(undefined, {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function padN(n, digits) {
    return String(n).padStart(digits, '0');
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
    const [betType, setBetType] = useState('number');
    const [selectedNumbers, setSelectedNumbers] = useState([]);
    const [panaInput, setPanaInput] = useState('');
    const [betAmount, setBetAmount] = useState('10');
    const [placing, setPlacing] = useState(false);
    const [betMessage, setBetMessage] = useState(null);
    const [betError, setBetError] = useState(null);
    const betsSectionRef = useRef(null);

    const loadDashboard = () => {
        setLoading(true);
        return axios
            .get(route('api.dashboard'))
            .then((response) => {
                setData(response.data);
                setError(null);
                const draws = response.data.upcoming_draws || [];
                setSelectedDrawId((prev) => {
                    if (prev && draws.some((d) => d.id === prev && d.board === board)) {
                        return prev;
                    }
                    // Kalyan: user must pick a market manually
                    if (board === 'matka') {
                        return null;
                    }
                    const preferred =
                        draws.find((d) => d.board === 'king' && d.status === 'open') ||
                        draws.find((d) => d.board === 'king');
                    return preferred?.id ?? null;
                });
            })
            .catch(() => {
                setError('Could not load dashboard data. Try again.');
            })
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        loadDashboard();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const betting = data?.betting || {};
    const minAmount = betting.min_amount ?? 1;
    const maxAmount = betting.max_amount ?? 100000;

    const boardDraws = useMemo(() => {
        const list = (data?.upcoming_draws || []).filter((d) => d.board === board);
        const q = marketSearch.trim().toLowerCase();
        if (!q) return list;
        return list.filter((d) => String(d.name || '').toLowerCase().includes(q));
    }, [data, board, marketSearch]);

    const selectedDraw = useMemo(
        () => data?.upcoming_draws?.find((d) => d.id === selectedDrawId) || null,
        [data, selectedDrawId],
    );

    const activeType = useMemo(() => {
        const types = selectedDraw?.bet_types || [];
        return types.find((t) => t.id === betType) || types.find((t) => t.open) || types[0] || null;
    }, [selectedDraw, betType]);

    const digits = activeType?.digits ?? 2;
    const minNumber = activeType?.min ?? 0;
    const maxNumber = activeType?.max ?? 99;
    const multiplier = activeType?.multiplier ?? 9;
    const amountValue = Number(betAmount) || 0;
    const potentialWin = amountValue > 0 ? amountValue * multiplier : 0;
    const isPana = digits === 3;

    const numberPool = useMemo(() => {
        if (isPana) return [];
        return Array.from({ length: maxNumber - minNumber + 1 }, (_, i) => minNumber + i);
    }, [minNumber, maxNumber, isPana]);

    useEffect(() => {
        if (!selectedDraw) return;
        const openType =
            selectedDraw.bet_types?.find((t) => t.open) ||
            selectedDraw.bet_types?.[0];
        setBetType(openType?.id || (board === 'matka' ? 'jodi' : 'number'));
        setSelectedNumbers([]);
        setPanaInput('');
    }, [selectedDrawId, board]); // eslint-disable-line react-hooks/exhaustive-deps

    const selectBoard = (next) => {
        setBoard(next);
        setMarketSearch('');
        setSelectedNumbers([]);
        setPanaInput('');
        setBetError(null);
        setBetMessage(null);
        if (next === 'matka') {
            setSelectedDrawId(null);
            return;
        }
        const first =
            (data?.upcoming_draws || []).find((d) => d.board === next && d.status === 'open') ||
            (data?.upcoming_draws || []).find((d) => d.board === next);
        setSelectedDrawId(first?.id ?? null);
    };

    const toggleNumber = (n) => {
        setBetMessage(null);
        setBetError(null);
        setSelectedNumbers([n]);
    };

    const placeBet = async () => {
        if (!selectedDraw) {
            setBetError('Please select a market.');
            return;
        }
        if (!activeType?.open) {
            setBetError('This bet type is closed for now.');
            return;
        }
        if (amountValue < minAmount || amountValue > maxAmount) {
            setBetError(
                `Amount must be between ${formatMoney(minAmount)} and ${formatMoney(maxAmount)}.`,
            );
            return;
        }

        let numbers = selectedNumbers;
        if (isPana) {
            if (!/^\d{3}$/.test(panaInput)) {
                setBetError('Enter a 3-digit pana (e.g. 257).');
                return;
            }
            numbers = [parseInt(panaInput, 10)];
        } else if (numbers.length !== 1) {
            setBetError(`Select 1 number (${padN(minNumber, digits)}–${padN(maxNumber, digits)}).`);
            return;
        }

        setPlacing(true);
        setBetError(null);
        setBetMessage(null);

        try {
            const response = await axios.post(route('api.bets.store'), {
                draw_id: selectedDraw.id,
                board: selectedDraw.board,
                bet_type: activeType.id,
                numbers,
                amount: amountValue,
            });
            setBetMessage(
                `${response.data.message} Potential win ${formatMoney(response.data.bet.potential_win)}.`,
            );
            setSelectedNumbers([]);
            setPanaInput('');
            await loadDashboard();
            window.setTimeout(() => {
                betsSectionRef.current?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                });
            }, 150);
        } catch (err) {
            const errors = err.response?.data?.errors;
            setBetError(
                errors?.numbers?.[0] ||
                    errors?.amount?.[0] ||
                    errors?.draw_id?.[0] ||
                    errors?.bet_type?.[0] ||
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
                        {board === 'matka'
                            ? 'Kalyan Matka: pehle market select karo, phir bet form khulega.'
                            : 'Satta King: amount choose karo, number lagao — 1₹ = 9₹.'}
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
                                {board === 'matka'
                                    ? 'Market select → bet type → amount → number'
                                    : 'Market → amount → number (00–99)'}
                            </p>
                        </div>
                        {selectedDraw && (
                            <p className="text-sm text-app-text">
                                Win {multiplier}×:{' '}
                                <span className="font-semibold text-gold">
                                    {potentialWin ? formatMoney(potentialWin) : '—'}
                                </span>
                            </p>
                        )}
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
                            placeholder="Search market…"
                            className="w-full rounded-xl border border-white/10 bg-navy-dark/50 px-4 py-2.5 text-sm text-white placeholder:text-app-muted focus:border-gold/40 focus:outline-none"
                        />
                    </div>

                    <div className="mb-4 max-h-52 space-y-2 overflow-y-auto pr-1">
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
                                        setPanaInput('');
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
                                            {draw.is_due && (
                                                <span className="ms-2 text-[10px] font-bold text-danger">
                                                    DUE
                                                </span>
                                            )}
                                            {draw.is_next_up && !draw.is_due && (
                                                <span className="ms-2 text-[10px] font-bold text-gold">
                                                    NEXT
                                                </span>
                                            )}
                                        </p>
                                        <p className="mt-0.5 text-xs text-app-muted">
                                            {draw.time_label || formatDrawTime(draw.draw_at)} ·{' '}
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
                                        {closed ? draw.current_result || 'Closed' : 'Open'}
                                    </span>
                                </button>
                            );
                        })}
                        {!loading && boardDraws.length === 0 && (
                            <p className="py-4 text-center text-sm text-app-muted">
                                {marketSearch
                                    ? 'No market matched.'
                                    : 'No live markets right now.'}
                            </p>
                        )}
                    </div>

                    {!selectedDraw && (
                        <div className="rounded-xl border border-dashed border-white/15 bg-navy-dark/30 px-4 py-6 text-center">
                            <p className="text-sm font-medium text-white">
                                {board === 'matka'
                                    ? 'Upar se koi ek Kalyan Matka market select karo'
                                    : 'Market select karo bet lagane ke liye'}
                            </p>
                            <p className="mt-1 text-xs text-app-muted">
                                Select ke baad amount aur number inputs yahan dikhenge.
                            </p>
                        </div>
                    )}

                    {selectedDraw && (
                        <>
                            <div className="mb-4 rounded-xl border border-gold/20 bg-gold/5 px-4 py-3">
                                <p className="text-xs text-app-muted">Selected market</p>
                                <p className="text-sm font-semibold text-gold">
                                    {selectedDraw.name}
                                </p>
                            </div>

                            <div className="mb-4">
                                <label className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-app-muted">
                                    Your amount (₹)
                                </label>
                                <div className="flex flex-wrap gap-2">
                                    {[10, 20, 50, 100, 500].map((preset) => (
                                        <button
                                            key={preset}
                                            type="button"
                                            onClick={() => setBetAmount(String(preset))}
                                            className={`rounded-lg px-3 py-1.5 text-xs font-semibold ${
                                                Number(betAmount) === preset
                                                    ? 'bg-gold text-navy-dark'
                                                    : 'bg-navy-dark text-app-muted ring-1 ring-white/10'
                                            }`}
                                        >
                                            ₹{preset}
                                        </button>
                                    ))}
                                    <input
                                        type="number"
                                        min={minAmount}
                                        max={maxAmount}
                                        step="1"
                                        value={betAmount}
                                        onChange={(e) => setBetAmount(e.target.value)}
                                        className="w-28 rounded-xl border border-white/10 bg-navy-dark/50 px-3 py-1.5 text-sm text-white focus:border-gold/40 focus:outline-none"
                                    />
                                </div>
                            </div>

                            {board === 'matka' && selectedDraw.bet_types?.length > 0 && (
                                <div className="mb-4">
                                    <p className="mb-2 text-xs font-medium uppercase tracking-wide text-app-muted">
                                        Matka bet type
                                    </p>
                                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                        {selectedDraw.bet_types.map((t) => (
                                            <button
                                                key={t.id}
                                                type="button"
                                                disabled={!t.open}
                                                onClick={() => {
                                                    setBetType(t.id);
                                                    setSelectedNumbers([]);
                                                    setPanaInput('');
                                                }}
                                                className={`rounded-xl border px-3 py-2 text-left text-xs transition ${
                                                    betType === t.id
                                                        ? 'border-gold bg-gold/10 text-gold'
                                                        : t.open
                                                          ? 'border-white/10 bg-navy-dark/40 text-app-text'
                                                          : 'cursor-not-allowed border-white/5 opacity-40'
                                                }`}
                                            >
                                                <p className="font-semibold">{t.label}</p>
                                                <p className="mt-0.5 text-[10px] text-app-muted">
                                                    {t.hint} · {t.multiplier}×
                                                </p>
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            )}

                            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <p className="text-xs font-medium uppercase tracking-wide text-app-muted">
                                    {isPana
                                        ? 'Enter pana (3 digits)'
                                        : `Pick number (${padN(minNumber, digits)}–${padN(maxNumber, digits)})`}
                                </p>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setSelectedNumbers([]);
                                        setPanaInput('');
                                    }}
                                    className="rounded-lg bg-white/5 px-3 py-1.5 text-xs font-medium text-app-muted"
                                >
                                    Clear
                                </button>
                            </div>

                            {isPana ? (
                                <input
                                    type="text"
                                    inputMode="numeric"
                                    maxLength={3}
                                    value={panaInput}
                                    onChange={(e) =>
                                        setPanaInput(
                                            e.target.value.replace(/\D/g, '').slice(0, 3),
                                        )
                                    }
                                    placeholder="e.g. 257"
                                    className="mb-4 w-full rounded-xl border border-white/10 bg-navy-dark/50 px-4 py-3 text-center text-2xl font-bold tracking-[0.3em] text-gold focus:border-gold/40 focus:outline-none"
                                />
                            ) : (
                                <div
                                    className={`mb-4 grid gap-1.5 ${
                                        digits === 1
                                            ? 'grid-cols-5 sm:grid-cols-10'
                                            : 'grid-cols-10'
                                    }`}
                                >
                                    {numberPool.map((n) => {
                                        const active = selectedNumbers.includes(n);
                                        return (
                                            <button
                                                key={n}
                                                type="button"
                                                onClick={() => toggleNumber(n)}
                                                className={`flex h-9 items-center justify-center rounded-lg text-xs font-semibold transition sm:h-10 sm:text-sm ${
                                                    active
                                                        ? 'bg-gold text-navy-dark'
                                                        : 'bg-navy-dark text-app-text hover:bg-blue'
                                                }`}
                                            >
                                                {padN(n, digits)}
                                            </button>
                                        );
                                    })}
                                </div>
                            )}

                            <button
                                type="button"
                                onClick={placeBet}
                                disabled={
                                    placing ||
                                    loading ||
                                    !selectedDraw ||
                                    !activeType?.open ||
                                    amountValue < minAmount
                                }
                                className="auth-btn"
                            >
                                {placing
                                    ? 'Placing bet…'
                                    : `Place bet · ${formatMoney(amountValue || 0)} → win ${formatMoney(potentialWin || 0)}`}
                            </button>
                        </>
                    )}
                </section>

                <div ref={betsSectionRef} className="space-y-6">
                    <section className="dash-fade rounded-2xl bg-card p-5 shadow-sm ring-1 ring-gold/25 sm:p-6">
                        <div className="mb-1 flex flex-wrap items-end justify-between gap-2">
                            <div>
                                <h2 className="font-display text-lg font-semibold text-gold">
                                    Running bets
                                </h2>
                                <p className="mt-0.5 text-xs text-app-muted">
                                    Abhi chal rahi bets — result aate hi yahan Won/Lost
                                    ban jayegi
                                </p>
                            </div>
                            <span className="rounded-full bg-gold/15 px-3 py-1 text-xs font-semibold text-gold">
                                {(data?.active_bets || []).length} active
                            </span>
                        </div>

                        {loading ? (
                            <div className="mt-4 space-y-3">
                                {[1, 2].map((i) => (
                                    <div
                                        key={i}
                                        className="h-28 animate-pulse rounded-xl bg-navy-dark"
                                    />
                                ))}
                            </div>
                        ) : (data?.active_bets || []).length ? (
                            <ul className="mt-4 space-y-3">
                                {data.active_bets.map((bet) => (
                                    <BetCard key={bet.id} bet={bet} />
                                ))}
                            </ul>
                        ) : (
                            <p className="mt-4 rounded-xl border border-dashed border-white/10 px-4 py-6 text-center text-sm text-app-muted">
                                Koi running bet nahi. Upar se market select karke bet
                                lagao — yahan dikhegi.
                            </p>
                        )}
                    </section>

                    <section className="dash-fade rounded-2xl bg-card p-5 shadow-sm ring-1 ring-white/10 sm:p-6">
                        <div className="mb-1 flex flex-wrap items-end justify-between gap-2">
                            <div>
                                <h2 className="font-display text-lg font-semibold text-gold">
                                    Recent bets
                                </h2>
                                <p className="mt-0.5 text-xs text-app-muted">
                                    Settle ho chuki bets — Won / Lost / Refund
                                </p>
                            </div>
                            <a
                                href="/history"
                                className="text-xs font-semibold text-gold hover:underline"
                            >
                                Full history →
                            </a>
                        </div>

                        {loading ? (
                            <div className="mt-4 space-y-3">
                                {[1, 2].map((i) => (
                                    <div
                                        key={i}
                                        className="h-28 animate-pulse rounded-xl bg-navy-dark"
                                    />
                                ))}
                            </div>
                        ) : (data?.recent_bets || []).length ? (
                            <ul className="mt-4 space-y-3">
                                {data.recent_bets.map((bet) => (
                                    <BetCard key={bet.id} bet={bet} />
                                ))}
                            </ul>
                        ) : (
                            <p className="mt-4 text-sm text-app-muted">
                                Abhi koi settled bet nahi. Result aane ke baad yahan
                                dikhega.
                            </p>
                        )}
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

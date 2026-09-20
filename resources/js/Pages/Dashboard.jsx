import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BetCard from '@/Components/BetCard';
import Modal from '@/Components/Modal';
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

let slipSeq = 1;

export default function Dashboard() {
    const { auth } = usePage().props;
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const [board, setBoard] = useState('king');
    const [marketSearch, setMarketSearch] = useState('');
    const [selectedDrawId, setSelectedDrawId] = useState(null);
    const [betType, setBetType] = useState('number');
    const [numberInput, setNumberInput] = useState('');
    const [betAmount, setBetAmount] = useState('');
    const [slip, setSlip] = useState([]);
    const [confirmOpen, setConfirmOpen] = useState(false);
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
                    return null;
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
    const wallet = data?.wallet_balance ?? 0;

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
        return (
            types.find((t) => t.id === betType) ||
            types.find((t) => t.open) ||
            types[0] ||
            null
        );
    }, [selectedDraw, betType]);

    const digits = activeType?.digits ?? 2;
    const minNumber = activeType?.min ?? 0;
    const maxNumber = activeType?.max ?? 99;
    const multiplier = activeType?.multiplier ?? 9;
    const amountValue = Number(betAmount) || 0;

    const slipTotal = useMemo(
        () => slip.reduce((sum, row) => sum + Number(row.amount || 0), 0),
        [slip],
    );
    const slipPotential = useMemo(
        () => slip.reduce((sum, row) => sum + Number(row.potential_win || 0), 0),
        [slip],
    );

    useEffect(() => {
        if (!selectedDraw) return;
        const openType =
            selectedDraw.bet_types?.find((t) => t.open) ||
            selectedDraw.bet_types?.[0];
        setBetType(openType?.id || (board === 'matka' ? 'jodi' : 'number'));
        setNumberInput('');
        setBetAmount('');
        setBetError(null);
    }, [selectedDrawId, board]); // eslint-disable-line react-hooks/exhaustive-deps

    const selectBoard = (next) => {
        setBoard(next);
        setMarketSearch('');
        setSelectedDrawId(null);
        setNumberInput('');
        setBetAmount('');
        setBetError(null);
        setBetMessage(null);
    };

    const parseNumberInput = () => {
        const raw = String(numberInput || '').trim();
        if (!/^\d+$/.test(raw)) {
            return { error: `Number enter karo (${padN(minNumber, digits)}–${padN(maxNumber, digits)}).` };
        }
        if (raw.length > digits) {
            return { error: `Maximum ${digits} digit number dalo.` };
        }
        const n = parseInt(raw, 10);
        if (Number.isNaN(n) || n < minNumber || n > maxNumber) {
            return {
                error: `Number ${padN(minNumber, digits)} se ${padN(maxNumber, digits)} ke beech hona chahiye.`,
            };
        }
        return { number: n, display: padN(n, digits) };
    };

    const addToSlip = () => {
        setBetMessage(null);
        setBetError(null);

        if (!selectedDraw) {
            setBetError('Pehle market select karo.');
            return;
        }
        if (!activeType?.open) {
            setBetError('Is bet type pe betting band hai.');
            return;
        }

        const parsed = parseNumberInput();
        if (parsed.error) {
            setBetError(parsed.error);
            return;
        }

        if (amountValue < minAmount || amountValue > maxAmount) {
            setBetError(
                `Amount ${formatMoney(minAmount)} se ${formatMoney(maxAmount)} ke beech dalo.`,
            );
            return;
        }

        const row = {
            key: `slip-${slipSeq++}`,
            draw_id: selectedDraw.id,
            board: selectedDraw.board,
            board_label: selectedDraw.board_label,
            market_name: selectedDraw.name,
            bet_type: activeType.id,
            bet_type_label: activeType.label,
            numbers: [parsed.number],
            number_display: parsed.display,
            amount: amountValue,
            multiplier,
            potential_win: amountValue * multiplier,
        };

        setSlip((prev) => [...prev, row]);
        setNumberInput('');
        setBetAmount('');
        setBetMessage('Slip me add ho gaya. Aur bets add kar sakte ho, phir Confirm.');
    };

    const removeFromSlip = (key) => {
        setSlip((prev) => prev.filter((row) => row.key !== key));
    };

    const openConfirm = () => {
        setBetError(null);
        if (slip.length === 0) {
            setBetError('Pehle kam se kam 1 bet slip me add karo.');
            return;
        }
        if (slipTotal > wallet) {
            setBetError(
                `Wallet me sirf ${formatMoney(wallet)} hai, slip total ${formatMoney(slipTotal)} hai. Deposit karo ya bets kam karo.`,
            );
            return;
        }
        setConfirmOpen(true);
    };

    const confirmPlaceAll = async () => {
        if (slip.length === 0) return;

        setPlacing(true);
        setBetError(null);
        setBetMessage(null);

        let ok = 0;
        let fail = 0;
        const remaining = [];

        for (const row of slip) {
            try {
                await axios.post(route('api.bets.store'), {
                    draw_id: row.draw_id,
                    board: row.board,
                    bet_type: row.bet_type,
                    numbers: row.numbers,
                    amount: row.amount,
                });
                ok += 1;
            } catch (err) {
                fail += 1;
                remaining.push(row);
                const errors = err.response?.data?.errors;
                setBetError(
                    errors?.amount?.[0] ||
                        errors?.numbers?.[0] ||
                        err.response?.data?.message ||
                        'Kuch bets fail ho gayi.',
                );
            }
        }

        setSlip(remaining);
        setConfirmOpen(false);
        setPlacing(false);
        await loadDashboard();

        if (ok > 0) {
            setBetMessage(
                fail > 0
                    ? `${ok} bet place hui, ${fail} fail. Fail wali slip me reh gayi.`
                    : `${ok} bet confirm ho gayi — Running bets me dekho.`,
            );
            window.setTimeout(() => {
                betsSectionRef.current?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                });
            }, 150);
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
                        Number → amount → Add bet → last me Confirm (popup).
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
                    <div className="mb-4">
                        <h2 className="font-display text-lg font-semibold text-gold">
                            Place bets
                        </h2>
                        <p className="mt-0.5 text-xs text-app-muted">
                            Market select → number type karo → amount → Add · last me Confirm
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
                                No live markets right now.
                            </p>
                        )}
                    </div>

                    {!selectedDraw ? (
                        <div className="rounded-xl border border-dashed border-white/15 bg-navy-dark/30 px-4 py-6 text-center">
                            <p className="text-sm font-medium text-white">
                                Pehle market select karo
                            </p>
                            <p className="mt-1 text-xs text-app-muted">
                                Uske baad number aur amount input dikhega.
                            </p>
                        </div>
                    ) : (
                        <>
                            <div className="mb-4 rounded-xl border border-gold/20 bg-gold/5 px-4 py-3">
                                <p className="text-xs text-app-muted">Selected market</p>
                                <p className="text-sm font-semibold text-gold">
                                    {selectedDraw.name}
                                </p>
                            </div>

                            {board === 'matka' && selectedDraw.bet_types?.length > 0 && (
                                <div className="mb-4">
                                    <p className="mb-2 text-xs font-medium uppercase tracking-wide text-app-muted">
                                        Bet type
                                    </p>
                                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                        {selectedDraw.bet_types.map((t) => (
                                            <button
                                                key={t.id}
                                                type="button"
                                                disabled={!t.open}
                                                onClick={() => {
                                                    setBetType(t.id);
                                                    setNumberInput('');
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

                            <div className="mb-4 grid gap-3 sm:grid-cols-2">
                                <label className="block">
                                    <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-app-muted">
                                        Number ({padN(minNumber, digits)}–
                                        {padN(maxNumber, digits)})
                                    </span>
                                    <input
                                        type="text"
                                        inputMode="numeric"
                                        maxLength={digits}
                                        value={numberInput}
                                        onChange={(e) =>
                                            setNumberInput(
                                                e.target.value.replace(/\D/g, '').slice(0, digits),
                                            )
                                        }
                                        placeholder={
                                            digits === 1
                                                ? 'e.g. 7'
                                                : digits === 3
                                                  ? 'e.g. 257'
                                                  : 'e.g. 45'
                                        }
                                        className="w-full rounded-xl border border-white/10 bg-navy-dark/50 px-4 py-3 text-center text-2xl font-bold tracking-[0.25em] text-gold focus:border-gold/40 focus:outline-none"
                                    />
                                </label>
                                <label className="block">
                                    <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-app-muted">
                                        Amount (₹)
                                    </span>
                                    <input
                                        type="number"
                                        min={minAmount}
                                        max={maxAmount}
                                        step="1"
                                        value={betAmount}
                                        onChange={(e) => setBetAmount(e.target.value)}
                                        placeholder="e.g. 50"
                                        className="w-full rounded-xl border border-white/10 bg-navy-dark/50 px-4 py-3 text-center text-2xl font-bold text-white focus:border-gold/40 focus:outline-none"
                                    />
                                    <p className="mt-1 text-[11px] text-app-muted">
                                        Win rate {multiplier}× · if win{' '}
                                        {amountValue
                                            ? formatMoney(amountValue * multiplier)
                                            : '—'}
                                    </p>
                                </label>
                            </div>

                            <button
                                type="button"
                                onClick={addToSlip}
                                disabled={!activeType?.open}
                                className="auth-btn mb-4"
                            >
                                Add bet to slip
                            </button>
                        </>
                    )}

                    <div className="rounded-xl border border-white/10 bg-navy-dark/40 p-4">
                        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p className="text-sm font-semibold text-white">
                                    Bet slip ({slip.length})
                                </p>
                                <p className="text-xs text-app-muted">
                                    Add karte raho · last me Confirm
                                </p>
                            </div>
                            {slip.length > 0 && (
                                <button
                                    type="button"
                                    onClick={() => setSlip([])}
                                    className="text-xs text-app-muted hover:text-white"
                                >
                                    Clear slip
                                </button>
                            )}
                        </div>

                        {slip.length === 0 ? (
                            <p className="py-4 text-center text-sm text-app-muted">
                                Slip khali hai. Number + amount daal ke Add bet dabao.
                            </p>
                        ) : (
                            <ul className="mb-4 max-h-56 space-y-2 overflow-y-auto">
                                {slip.map((row) => (
                                    <li
                                        key={row.key}
                                        className="flex items-start justify-between gap-3 rounded-lg border border-white/10 bg-card/60 px-3 py-2"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-semibold text-white">
                                                {row.market_name}
                                            </p>
                                            <p className="text-xs text-app-muted">
                                                {row.board_label} · {row.bet_type_label} · No.{' '}
                                                <span className="font-semibold text-gold">
                                                    {row.number_display}
                                                </span>{' '}
                                                · {formatMoney(row.amount)} →{' '}
                                                {formatMoney(row.potential_win)}
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => removeFromSlip(row.key)}
                                            className="shrink-0 text-xs text-danger hover:underline"
                                        >
                                            Remove
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}

                        <div className="mb-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span className="text-app-muted">
                                Total stake:{' '}
                                <span className="font-semibold text-white">
                                    {formatMoney(slipTotal)}
                                </span>
                            </span>
                            <span className="text-app-muted">
                                If all win:{' '}
                                <span className="font-semibold text-gold">
                                    {formatMoney(slipPotential)}
                                </span>
                            </span>
                        </div>

                        <button
                            type="button"
                            onClick={openConfirm}
                            disabled={slip.length === 0 || placing}
                            className="auth-btn"
                        >
                            Confirm {slip.length || ''} bet
                            {slip.length === 1 ? '' : 's'}
                        </button>
                    </div>
                </section>

                <div ref={betsSectionRef} className="space-y-6">
                    <section className="dash-fade rounded-2xl bg-card p-5 shadow-sm ring-1 ring-gold/25 sm:p-6">
                        <div className="mb-1 flex flex-wrap items-end justify-between gap-2">
                            <div>
                                <h2 className="font-display text-lg font-semibold text-gold">
                                    Running bets
                                </h2>
                                <p className="mt-0.5 text-xs text-app-muted">
                                    Confirm ke baad yahan dikhengi
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
                                Abhi koi running bet nahi.
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
                                    Won / Lost / Refund
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
                                Abhi koi settled bet nahi.
                            </p>
                        )}
                    </section>
                </div>
            </div>

            <Modal
                show={confirmOpen}
                onClose={() => !placing && setConfirmOpen(false)}
                maxWidth="lg"
            >
                <div className="bg-navy-dark p-5 text-white sm:p-6">
                    <h3 className="font-display text-xl font-semibold text-gold">
                        Confirm bets
                    </h3>
                    <p className="mt-1 text-sm text-app-muted">
                        Check karo — kis market pe kitna lagaya hai
                    </p>

                    <ul className="mt-4 max-h-64 space-y-2 overflow-y-auto">
                        {slip.map((row) => (
                            <li
                                key={row.key}
                                className="rounded-xl border border-white/10 bg-card/80 px-3 py-2"
                            >
                                <p className="text-sm font-semibold text-white">
                                    {row.market_name}
                                </p>
                                <p className="mt-0.5 text-xs text-app-muted">
                                    {row.board_label} · {row.bet_type_label}
                                </p>
                                <div className="mt-2 flex flex-wrap gap-3 text-sm">
                                    <span>
                                        No.{' '}
                                        <strong className="text-gold">
                                            {row.number_display}
                                        </strong>
                                    </span>
                                    <span>
                                        Amount{' '}
                                        <strong>{formatMoney(row.amount)}</strong>
                                    </span>
                                    <span>
                                        If win{' '}
                                        <strong className="text-gold">
                                            {formatMoney(row.potential_win)}
                                        </strong>
                                    </span>
                                </div>
                            </li>
                        ))}
                    </ul>

                    <div className="mt-4 flex flex-wrap justify-between gap-2 border-t border-white/10 pt-3 text-sm">
                        <span>
                            Total: <strong>{formatMoney(slipTotal)}</strong>
                        </span>
                        <span>
                            Wallet: <strong>{formatMoney(wallet)}</strong>
                        </span>
                    </div>

                    <div className="mt-5 flex flex-wrap gap-3">
                        <button
                            type="button"
                            disabled={placing}
                            onClick={() => setConfirmOpen(false)}
                            className="rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold text-white"
                        >
                            Back
                        </button>
                        <button
                            type="button"
                            disabled={placing}
                            onClick={confirmPlaceAll}
                            className="auth-btn flex-1"
                        >
                            {placing
                                ? 'Placing…'
                                : `Confirm & place · ${formatMoney(slipTotal)}`}
                        </button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}

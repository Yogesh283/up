import StatusBadge from '@/Components/StatusBadge';
import { formatDateTime, formatMoney } from '@/lib/format';

export default function BetCard({ bet }) {
    const isActive = bet.status === 'pending' || bet.is_active;
    const borderClass = isActive
        ? 'border-gold/40 bg-gold/5'
        : bet.status === 'won'
          ? 'border-success/30 bg-success/5'
          : bet.status === 'lost'
            ? 'border-white/10 bg-navy-dark/40'
            : 'border-white/10 bg-blue/20';

    return (
        <li className={`rounded-xl border px-4 py-3 ${borderClass}`}>
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="rounded-md bg-white/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-app-muted">
                            {bet.board_label || bet.board || 'Market'}
                        </span>
                        <span className="rounded-md bg-blue/40 px-2 py-0.5 text-[10px] font-semibold text-app-text">
                            {bet.bet_type_label || bet.bet_type || 'Bet'}
                        </span>
                        {bet.ref && (
                            <span className="text-[10px] text-app-muted">{bet.ref}</span>
                        )}
                    </div>
                    <p className="mt-1.5 truncate text-sm font-semibold text-white">
                        {bet.market_name || bet.draw_name}
                    </p>
                    <p className="mt-0.5 text-xs text-app-muted">
                        {formatDateTime(bet.created_at)}
                        {bet.draw_at ? ` · Draw ${formatDateTime(bet.draw_at)}` : ''}
                    </p>
                </div>
                <StatusBadge status={bet.status} />
            </div>

            <div className="mt-3 grid grid-cols-3 gap-2 text-center">
                <div className="rounded-lg bg-navy-dark/50 px-2 py-2">
                    <p className="text-[10px] uppercase text-app-muted">Number</p>
                    <p className="mt-0.5 font-display text-lg font-bold tabular-nums text-gold">
                        {bet.number_text ||
                            (bet.numbers_display || bet.numbers || []).join(', ') ||
                            '—'}
                    </p>
                </div>
                <div className="rounded-lg bg-navy-dark/50 px-2 py-2">
                    <p className="text-[10px] uppercase text-app-muted">Stake</p>
                    <p className="mt-0.5 text-sm font-semibold text-white">
                        {formatMoney(bet.amount)}
                    </p>
                </div>
                <div className="rounded-lg bg-navy-dark/50 px-2 py-2">
                    <p className="text-[10px] uppercase text-app-muted">
                        {isActive ? 'If win' : bet.status === 'won' ? 'Won' : 'Payout'}
                    </p>
                    <p className="mt-0.5 text-sm font-semibold text-gold">
                        {isActive
                            ? formatMoney(bet.potential_win || 0)
                            : bet.prize > 0
                              ? formatMoney(bet.prize)
                              : '₹0'}
                    </p>
                </div>
            </div>

            <p className="mt-2 text-xs text-app-muted">
                {bet.status_hint ||
                    (isActive
                        ? 'Result aate hi auto settle hoga'
                        : bet.result_value
                          ? `Result: ${bet.result_value}`
                          : '')}
                {!isActive && bet.result_value ? (
                    <span className="ms-1 font-semibold text-white">
                        · Result {bet.result_value}
                    </span>
                ) : null}
            </p>
        </li>
    );
}

export default function StatusBadge({ status }) {
    const styles = {
        won: 'bg-success/15 text-success',
        lost: 'bg-danger/15 text-danger',
        pending: 'bg-gold/15 text-gold',
        refunded: 'bg-blue/30 text-app-text',
        completed: 'bg-success/15 text-success',
        failed: 'bg-danger/15 text-danger',
        processing: 'bg-blue/40 text-app-text',
        open: 'bg-gold/15 text-gold',
        closed: 'bg-white/10 text-app-muted',
        joined: 'bg-success/15 text-success',
    };

    return (
        <span
            className={`inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-medium capitalize ${
                styles[status] || 'bg-white/10 text-app-muted'
            }`}
        >
            {status}
        </span>
    );
}

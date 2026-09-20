export default function PageHeader({ eyebrow, title, subtitle }) {
    return (
        <div className="dash-fade mb-6 sm:mb-8">
            {eyebrow && (
                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-gold">
                    {eyebrow}
                </p>
            )}
            <h1 className="mt-1 font-display text-2xl font-semibold tracking-tight text-white sm:text-3xl">
                {title}
            </h1>
            {subtitle && (
                <p className="mt-1 text-sm text-app-muted">{subtitle}</p>
            )}
        </div>
    );
}

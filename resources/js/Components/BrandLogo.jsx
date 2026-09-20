const SRC = {
    mark: '/images/brand/logo-updown.png',
    up: '/images/brand/logo-up.png',
    down: '/images/brand/logo-down.png',
};

const SIZE = {
    sm: 'h-8 w-8',
    md: 'h-9 w-9',
    lg: 'h-11 w-11',
};

/**
 * Short UpDown brand mark (no letter L).
 * variant: mark | up | down
 */
export default function BrandLogo({
    variant = 'mark',
    size = 'md',
    showWordmark = false,
    className = '',
}) {
    const src = SRC[variant] ?? SRC.mark;
    const box = SIZE[size] ?? SIZE.md;

    return (
        <span className={`inline-flex items-center gap-2.5 ${className}`}>
            <img
                src={src}
                alt="UpDown"
                width={44}
                height={44}
                className={`${box} shrink-0 rounded-xl object-cover shadow-sm ring-1 ring-white/10`}
                decoding="async"
            />
            {showWordmark && (
                <span className="font-display text-lg font-semibold tracking-tight text-white sm:text-xl">
                    <span className="text-gold">Up</span>Down
                </span>
            )}
        </span>
    );
}

export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center rounded-md border border-transparent bg-gold px-4 py-2 text-xs font-semibold uppercase tracking-widest text-navy-dark transition duration-150 ease-in-out hover:bg-gold-bright focus:bg-gold-bright focus:outline-none focus:ring-2 focus:ring-gold focus:ring-offset-2 focus:ring-offset-navy active:bg-gold-dark ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}

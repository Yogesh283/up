export default function Checkbox({ className = '', ...props }) {
    return (
        <input
            {...props}
            type="checkbox"
            className={
                'rounded border-white/20 text-gold shadow-sm focus:ring-gold ' +
                className
            }
        />
    );
}

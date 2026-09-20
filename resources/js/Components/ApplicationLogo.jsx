import BrandLogo from '@/Components/BrandLogo';

/** @deprecated Prefer BrandLogo — kept for Breeze compatibility */
export default function ApplicationLogo({ className = '', ...props }) {
    return (
        <BrandLogo
            variant="mark"
            size="lg"
            className={className}
            {...props}
        />
    );
}

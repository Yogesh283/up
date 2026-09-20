import AuthLayout from '@/Layouts/AuthLayout';

export default function GuestLayout({ children, title, subtitle }) {
    return (
        <AuthLayout title={title} subtitle={subtitle}>
            {children}
        </AuthLayout>
    );
}

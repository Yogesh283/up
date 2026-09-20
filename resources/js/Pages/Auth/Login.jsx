import AuthLayout from '@/Layouts/AuthLayout';
import {
    COUNTRY_CODES,
    DEFAULT_COUNTRY_CODE,
} from '@/data/countryCodes';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login({ status }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        country_code: DEFAULT_COUNTRY_CODE,
        mobile: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    const onMobileChange = (e) => {
        const digitsOnly = e.target.value.replace(/\D/g, '').slice(0, 15);
        setData('mobile', digitsOnly);
    };

    return (
        <AuthLayout
            compact
            title="Welcome back"
            subtitle="Sign in with your mobile number."
        >
            <Head title="Log in" />

            {status && (
                <div className="mb-3 rounded-lg border border-success/30 bg-success/10 px-3 py-2 text-xs text-success">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-2">
                <div>
                    <label htmlFor="mobile" className="auth-label">
                        Mobile number
                    </label>
                    <div className="auth-phone-bar">
                        <select
                            id="country_code"
                            name="country_code"
                            value={data.country_code}
                            className="auth-country-select"
                            aria-label="Country code"
                            onChange={(e) =>
                                setData('country_code', e.target.value)
                            }
                        >
                            {COUNTRY_CODES.map((item) => (
                                <option key={item.country} value={item.code}>
                                    {item.flag} {item.code}
                                </option>
                            ))}
                        </select>
                        <input
                            id="mobile"
                            type="tel"
                            name="mobile"
                            inputMode="numeric"
                            value={data.mobile}
                            className="auth-phone-input"
                            autoComplete="tel-national"
                            autoFocus
                            placeholder="9876543210"
                            required
                            onChange={onMobileChange}
                        />
                    </div>
                    {(errors.mobile || errors.country_code) && (
                        <p className="mt-1 text-xs text-danger">
                            {errors.mobile || errors.country_code}
                        </p>
                    )}
                </div>

                <div>
                    <label htmlFor="password" className="auth-label">
                        Password
                    </label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="auth-field"
                        autoComplete="current-password"
                        placeholder="Your password"
                        required
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    {errors.password && (
                        <p className="mt-1 text-xs text-danger">
                            {errors.password}
                        </p>
                    )}
                </div>

                <label className="flex items-center gap-2 pt-0.5">
                    <input
                        type="checkbox"
                        name="remember"
                        checked={data.remember}
                        onChange={(e) => setData('remember', e.target.checked)}
                        className="h-3.5 w-3.5 rounded border-white/20 text-gold focus:ring-gold"
                    />
                    <span className="text-xs text-app-muted">Remember me</span>
                </label>

                <button
                    type="submit"
                    className="auth-btn !mt-1.5"
                    disabled={processing}
                >
                    {processing ? 'Signing in…' : 'Sign in'}
                </button>
            </form>

            <p className="mt-3 text-center text-xs text-app-muted">
                New here?{' '}
                <Link href={route('register')} className="auth-link">
                    Create an account
                </Link>
            </p>
        </AuthLayout>
    );
}

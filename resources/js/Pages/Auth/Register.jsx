import AuthLayout from '@/Layouts/AuthLayout';
import {
    COUNTRY_CODES,
    DEFAULT_COUNTRY_CODE,
} from '@/data/countryCodes';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        country_code: DEFAULT_COUNTRY_CODE,
        mobile: '',
        password: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('register'), {
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
            title="Create account"
            subtitle="Join with your mobile number."
        >
            <Head title="Register" />

            <form onSubmit={submit} className="space-y-2">
                <div>
                    <label htmlFor="name" className="auth-label">
                        Full name
                    </label>
                    <input
                        id="name"
                        name="name"
                        value={data.name}
                        className="auth-field"
                        autoComplete="name"
                        autoFocus
                        placeholder="Your name"
                        required
                        onChange={(e) => setData('name', e.target.value)}
                    />
                    {errors.name && (
                        <p className="mt-1 text-xs text-danger">{errors.name}</p>
                    )}
                </div>

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
                        autoComplete="new-password"
                        placeholder="Create a password"
                        required
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    {errors.password && (
                        <p className="mt-1 text-xs text-danger">
                            {errors.password}
                        </p>
                    )}
                </div>

                <button
                    type="submit"
                    className="auth-btn !mt-1.5"
                    disabled={processing}
                >
                    {processing ? 'Creating…' : 'Create account'}
                </button>
            </form>

            <p className="mt-3 text-center text-xs text-app-muted">
                Already have an account?{' '}
                <Link href={route('login')} className="auth-link">
                    Sign in
                </Link>
            </p>
        </AuthLayout>
    );
}

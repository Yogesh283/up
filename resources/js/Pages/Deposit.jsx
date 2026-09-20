import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import StatusBadge from '@/Components/StatusBadge';
import { formatDateTime, formatMoney } from '@/lib/format';
import useApiData from '@/hooks/useApiData';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { useMemo, useState } from 'react';

export default function Deposit() {
    const { data, loading, error, reload } = useApiData('api.deposit');
    const [amount, setAmount] = useState('');
    const [method, setMethod] = useState('upi');
    const [submitting, setSubmitting] = useState(false);
    const [message, setMessage] = useState(null);
    const [formError, setFormError] = useState(null);

    const quickAmounts = useMemo(
        () => data?.quick_amounts || [100, 200, 500, 1000],
        [data],
    );

    const submit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setMessage(null);
        setFormError(null);

        try {
            const response = await axios.post(route('api.deposit.store'), {
                amount: Number(amount),
                method,
            });
            setMessage(response.data.message);
            setAmount('');
            reload();
        } catch (err) {
            const errors = err.response?.data?.errors;
            setFormError(
                errors?.amount?.[0] ||
                    errors?.method?.[0] ||
                    'Deposit failed. Please try again.',
            );
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Deposit" />

            <div className="mx-auto max-w-6xl px-4 py-6 pb-24 sm:px-6 sm:py-8 sm:pb-8 lg:px-8">
                <PageHeader
                    eyebrow="Wallet"
                    title="Deposit"
                    subtitle="Add money to your Lottery wallet."
                />

                {(error || formError) && (
                    <div className="mb-6 rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
                        {formError || error}
                    </div>
                )}
                {message && (
                    <div className="mb-6 rounded-xl border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">
                        {message}
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="rounded-2xl bg-card p-5 shadow-sm ring-1 ring-white/10 sm:p-6">
                        <p className="text-xs text-app-muted">Available balance</p>
                        <p className="mt-1 font-display text-3xl font-semibold text-gold">
                            {loading ? '—' : formatMoney(data?.balance)}
                        </p>

                        <form onSubmit={submit} className="mt-5 space-y-4">
                            <div>
                                <label htmlFor="amount" className="auth-label">
                                    Amount
                                </label>
                                <input
                                    id="amount"
                                    type="number"
                                    min={data?.min_amount || 100}
                                    max={data?.max_amount || 50000}
                                    value={amount}
                                    onChange={(e) => setAmount(e.target.value)}
                                    className="auth-field"
                                    placeholder="Enter amount"
                                    required
                                />
                                <div className="mt-2 flex flex-wrap gap-2">
                                    {quickAmounts.map((value) => (
                                        <button
                                            key={value}
                                            type="button"
                                            onClick={() => setAmount(String(value))}
                                            className="rounded-lg bg-navy-dark px-3 py-1.5 text-xs font-medium text-app-text transition hover:bg-gold/10 hover:text-gold"
                                        >
                                            {formatMoney(value)}
                                        </button>
                                    ))}
                                </div>
                            </div>

                            <div>
                                <p className="auth-label">Payment method</p>
                                <div className="space-y-2">
                                    {(data?.methods || []).map((item) => (
                                        <label
                                            key={item.id}
                                            className={`flex cursor-pointer items-center justify-between rounded-xl border px-3 py-3 ${
                                                method === item.id
                                                    ? 'border-gold bg-gold/10'
                                                    : 'border-white/10'
                                            }`}
                                        >
                                            <span>
                                                <span className="block text-sm font-medium text-white">
                                                    {item.label}
                                                </span>
                                                <span className="text-xs text-app-muted">
                                                    {item.hint}
                                                </span>
                                            </span>
                                            <input
                                                type="radio"
                                                name="method"
                                                value={item.id}
                                                checked={method === item.id}
                                                onChange={() => setMethod(item.id)}
                                                className="text-gold focus:ring-gold"
                                            />
                                        </label>
                                    ))}
                                </div>
                            </div>

                            <button
                                type="submit"
                                className="auth-btn"
                                disabled={submitting || loading}
                            >
                                {submitting ? 'Processing…' : 'Deposit now'}
                            </button>
                        </form>
                    </section>

                    <section className="rounded-2xl bg-card p-5 shadow-sm ring-1 ring-white/10 sm:p-6">
                        <h2 className="mb-4 font-display text-lg font-semibold text-gold">
                            Recent deposits
                        </h2>
                        <ul className="space-y-3">
                            {loading
                                ? [1, 2].map((i) => (
                                      <li
                                          key={i}
                                          className="h-14 animate-pulse rounded-xl bg-navy-dark"
                                      />
                                  ))
                                : data?.recent?.map((item) => (
                                      <li
                                          key={item.id}
                                          className="flex items-center justify-between rounded-xl border border-white/10 px-3 py-3"
                                      >
                                          <div>
                                              <p className="text-sm font-semibold text-white">
                                                  {formatMoney(item.amount)}
                                              </p>
                                              <p className="text-xs text-app-muted">
                                                  {item.id} · {item.method} ·{' '}
                                                  {formatDateTime(item.created_at)}
                                              </p>
                                          </div>
                                          <StatusBadge status={item.status} />
                                      </li>
                                  ))}
                        </ul>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

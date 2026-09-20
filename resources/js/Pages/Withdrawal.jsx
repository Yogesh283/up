import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import StatusBadge from '@/Components/StatusBadge';
import { formatDateTime, formatMoney } from '@/lib/format';
import useApiData from '@/hooks/useApiData';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { useState } from 'react';

export default function Withdrawal() {
    const { data, loading, error, reload } = useApiData('api.withdrawal');
    const [amount, setAmount] = useState('');
    const [method, setMethod] = useState('upi');
    const [account, setAccount] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [message, setMessage] = useState(null);
    const [formError, setFormError] = useState(null);

    const submit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setMessage(null);
        setFormError(null);

        try {
            const response = await axios.post(route('api.withdrawal.store'), {
                amount: Number(amount),
                method,
                account,
            });
            setMessage(response.data.message);
            setAmount('');
            setAccount('');
            reload();
        } catch (err) {
            const errors = err.response?.data?.errors;
            setFormError(
                errors?.amount?.[0] ||
                    errors?.account?.[0] ||
                    errors?.method?.[0] ||
                    'Withdrawal failed. Please try again.',
            );
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Withdrawal" />

            <div className="mx-auto max-w-6xl px-4 py-6 pb-24 sm:px-6 sm:py-8 sm:pb-8 lg:px-8">
                <PageHeader
                    eyebrow="Wallet"
                    title="Withdrawal"
                    subtitle="Withdraw winnings to UPI or bank account."
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
                        <p className="text-xs text-app-muted">Withdrawable balance</p>
                        <p className="mt-1 font-display text-3xl font-semibold text-gold">
                            {loading ? '—' : formatMoney(data?.balance)}
                        </p>
                        <p className="mt-1 text-xs text-app-muted">
                            Min {formatMoney(data?.min_amount || 200)} · Max{' '}
                            {formatMoney(data?.max_amount || 0)}
                        </p>

                        <form onSubmit={submit} className="mt-5 space-y-4">
                            <div>
                                <label htmlFor="amount" className="auth-label">
                                    Amount
                                </label>
                                <input
                                    id="amount"
                                    type="number"
                                    min={data?.min_amount || 200}
                                    max={data?.max_amount || data?.balance || 0}
                                    value={amount}
                                    onChange={(e) => setAmount(e.target.value)}
                                    className="auth-field"
                                    placeholder="Enter amount"
                                    required
                                />
                            </div>

                            <div>
                                <p className="auth-label">Withdraw to</p>
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

                            <div>
                                <label htmlFor="account" className="auth-label">
                                    {method === 'bank'
                                        ? 'Account / IFSC details'
                                        : 'UPI ID'}
                                </label>
                                <input
                                    id="account"
                                    type="text"
                                    value={account}
                                    onChange={(e) => setAccount(e.target.value)}
                                    className="auth-field"
                                    placeholder={
                                        method === 'bank'
                                            ? 'Account no + IFSC'
                                            : 'name@upi'
                                    }
                                    required
                                />
                            </div>

                            <button
                                type="submit"
                                className="auth-btn"
                                disabled={submitting || loading}
                            >
                                {submitting ? 'Submitting…' : 'Request withdrawal'}
                            </button>
                        </form>
                    </section>

                    <section className="rounded-2xl bg-card p-5 shadow-sm ring-1 ring-white/10 sm:p-6">
                        <h2 className="mb-4 font-display text-lg font-semibold text-gold">
                            Recent withdrawals
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

// resources/js/pages/customers/show.tsx

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useEffect } from 'react';
import { ArrowLeft, Edit, MapPin, Phone, ShoppingBag, UserCircle } from 'lucide-react';

interface Customer {
    id: number;
    name: string;
    phone: string | null;
    address: string | null;
    notes: string | null;
    status: 'active' | 'inactive';
    creator: { id: number; name: string; email: string } | null;
    created_at: string;
    updated_at: string;
}

interface LedgerRow {
    id: number;
    type: string;
    amount_afn: string;
    balance_after: string;
    notes: string | null;
    created_at: string;
}

interface CustomerReceipt {
    id: number;
    receipt_number: string;
    receipt_type: 'sale_invoice' | 'payment_receipt';
    amount_afn: string;
    remaining_due_afn: string;
    previous_due_afn: number;
    invoice_number: string | null;
    created_at: string;
}

interface Props {
    customer: Customer;
    transactions: LedgerRow[];
    receipts: CustomerReceipt[];
    stats: {
        total_sales: number;
        total_amount: number;
        total_paid: number;
        current_due_afn: number | string;
        last_sale_at: string | null;
        last_payment_at: string | null;
    };
}

export default function CustomersShow({ customer, transactions, receipts, stats }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Customers', href: '/customers' },
        { title: customer.name, href: `/customers/${customer.id}` },
    ];

    const formatDate = (date: string | null) => {
        if (!date) return '—';
        return new Date(date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const formatPrice = (value: string | number) => {
        const num = typeof value === 'string' ? parseFloat(value) : value;
        return new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(num);
    };

    const paymentForm = useForm({
        amount_afn: '',
        payment_method: 'cash',
        reference_number: '',
        notes: '',
    });

    const currentDue = Number(stats.current_due_afn ?? 0);

    useEffect(() => {
        if (window.location.hash === '#pay-due') {
            document.getElementById('pay-due')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, []);

    const submitPayment = (e: FormEvent) => {
        e.preventDefault();
        paymentForm.post(`/customers/${customer.id}/payments`);
    };

    const statusColors = {
        active: 'bg-green-100 text-green-800 border-green-200',
        inactive: 'bg-gray-100 text-gray-800 border-gray-200',
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`View ${customer.name}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-4">
                        <div className="flex h-16 w-16 items-center justify-center rounded-full border bg-gray-100">
                            <UserCircle className="h-9 w-9 text-gray-600" />
                        </div>
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-2xl font-bold tracking-tight">{customer.name}</h1>
                                <span className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${statusColors[customer.status]}`}>
                                    {customer.status.charAt(0).toUpperCase() + customer.status.slice(1)}
                                </span>
                            </div>
                            {customer.phone && (
                                <div className="mt-1 flex items-center gap-1 text-sm text-gray-500">
                                    <Phone className="h-3 w-3" />
                                    <span dir="ltr">{customer.phone}</span>
                                </div>
                            )}
                        </div>
                    </div>
                    <div className="flex gap-2">
                        {currentDue > 0 && (
                            <a href="#pay-due" className="bg-primary text-primary-foreground inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium">
                                Pay due
                            </a>
                        )}
                        <Link href={`/customers/${customer.id}/edit`}>
                            <button className="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                                <Edit className="h-4 w-4" /> Edit
                            </button>
                        </Link>
                        <Link href="/customers">
                            <button className="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                                <ArrowLeft className="h-4 w-4" /> Back
                            </button>
                        </Link>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Info */}
                    <div className="rounded-lg border bg-white shadow-sm lg:col-span-2">
                        <div className="border-b px-6 py-4">
                            <h2 className="text-lg font-semibold">Customer Information</h2>
                        </div>
                        <div className="space-y-4 p-6">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <p className="text-sm text-gray-500">Name</p>
                                    <p className="text-lg font-medium">{customer.name}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">Phone</p>
                                    <p className="font-medium" dir="ltr">
                                        {customer.phone || '—'}
                                    </p>
                                </div>
                            </div>

                            {customer.address && (
                                <>
                                    <hr className="border-gray-200" />
                                    <div className="flex items-start gap-3">
                                        <MapPin className="text-muted-foreground mt-0.5 h-5 w-5" />
                                        <span>{customer.address}</span>
                                    </div>
                                </>
                            )}

                            {customer.notes && (
                                <>
                                    <hr className="border-gray-200" />
                                    <div>
                                        <p className="text-sm text-gray-500">Notes</p>
                                        <p className="mt-1 text-sm">{customer.notes}</p>
                                    </div>
                                </>
                            )}

                            <hr className="border-gray-200" />

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <p className="text-sm text-gray-500">Created By</p>
                                    <p className="font-medium">{customer.creator?.name || 'System'}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">Created At</p>
                                    <p className="text-sm">{formatDate(customer.created_at)}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Account */}
                    <div className="rounded-lg border bg-white shadow-sm">
                        <div className="border-b px-6 py-4">
                            <h2 className="text-lg font-semibold">Customer account</h2>
                        </div>
                        <div className="space-y-4 p-6">
                            <div className="flex items-center justify-between border-b pb-3">
                                <span className="text-sm text-gray-500">Name</span>
                                <span className="font-semibold">{customer.name}</span>
                            </div>
                            <div className="flex items-center justify-between border-b pb-3">
                                <span className="text-muted-foreground text-sm">Current due</span>
                                <span className="font-mono text-xl font-bold">{formatPrice(currentDue)} AFN</span>
                            </div>
                            <div className="flex items-center justify-between border-b pb-3">
                                <span className="text-sm text-gray-500">Last purchase</span>
                                <span className="text-sm">{formatDate(stats.last_sale_at)}</span>
                            </div>
                            <div className="flex items-center justify-between border-b pb-3">
                                <span className="text-sm text-gray-500">Last payment</span>
                                <span className="text-sm">{formatDate(stats.last_payment_at)}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2 text-sm text-gray-500">
                                    <ShoppingBag className="h-4 w-4" />
                                    Sales count
                                </div>
                                <span className="font-bold">{stats.total_sales}</span>
                            </div>
                        </div>

                        {currentDue > 0 && (
                            <form id="pay-due" onSubmit={submitPayment} className="space-y-3 border-t px-6 py-4">
                                <p className="text-sm font-semibold">Pay due</p>
                                <p className="text-muted-foreground text-xs">Current due {formatPrice(currentDue)} AFN</p>
                                <label className="block text-sm font-medium">Payment amount</label>
                                <input
                                    type="number"
                                    min="0.01"
                                    max={currentDue}
                                    step="0.01"
                                    required
                                    value={paymentForm.data.amount_afn}
                                    onChange={(e) => paymentForm.setData('amount_afn', e.target.value)}
                                    placeholder="500"
                                    className="w-full rounded-md border px-3 py-2 text-sm"
                                />
                                {paymentForm.errors.amount_afn && (
                                    <p className="text-xs text-red-600">{paymentForm.errors.amount_afn}</p>
                                )}
                                <button
                                    type="submit"
                                    disabled={paymentForm.processing}
                                    className="bg-primary text-primary-foreground w-full rounded-md px-4 py-2 text-sm font-medium disabled:opacity-60"
                                >
                                    Save payment and print
                                </button>
                            </form>
                        )}
                    </div>
                </div>

                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-6 py-4">
                        <h2 className="text-lg font-semibold">Receipts</h2>
                        <p className="text-muted-foreground text-sm">Current due {formatPrice(currentDue)} AFN</p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50 text-left text-gray-500">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Date</th>
                                    <th className="px-4 py-3 font-medium">Receipt</th>
                                    <th className="px-4 py-3 font-medium">Type</th>
                                    <th className="px-4 py-3 text-right font-medium">Previous due</th>
                                    <th className="px-4 py-3 text-right font-medium">This receipt</th>
                                    <th className="px-4 py-3 text-right font-medium">Remaining</th>
                                    <th className="px-4 py-3 font-medium">Print</th>
                                </tr>
                            </thead>
                            <tbody>
                                {receipts.length === 0 && (
                                    <tr>
                                        <td colSpan={7} className="px-4 py-6 text-center text-gray-400">
                                            No receipts yet
                                        </td>
                                    </tr>
                                )}
                                {receipts.map((receipt) => (
                                    <tr key={receipt.id} className="border-t">
                                        <td className="px-4 py-3">{formatDate(receipt.created_at)}</td>
                                        <td className="px-4 py-3 font-mono text-xs">
                                            {receipt.receipt_number}
                                            {receipt.invoice_number && (
                                                <div className="text-muted-foreground">{receipt.invoice_number}</div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">{receipt.receipt_type === 'payment_receipt' ? 'Debt payment' : 'Invoice'}</td>
                                        <td className="px-4 py-3 text-right font-mono">{formatPrice(receipt.previous_due_afn)}</td>
                                        <td className="px-4 py-3 text-right font-mono font-semibold">{formatPrice(receipt.amount_afn)}</td>
                                        <td className="px-4 py-3 text-right font-mono">{formatPrice(receipt.remaining_due_afn)}</td>
                                        <td className="px-4 py-3">
                                            <a className="underline" href={`/receipts/${receipt.id}/print`} target="_blank" rel="noreferrer">
                                                Print
                                            </a>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-6 py-4">
                        <h2 className="text-lg font-semibold">Ledger</h2>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50 text-left text-gray-500">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Date</th>
                                    <th className="px-4 py-3 font-medium">Type</th>
                                    <th className="px-4 py-3 font-medium">Amount</th>
                                    <th className="px-4 py-3 font-medium">Balance</th>
                                    <th className="px-4 py-3 font-medium">Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                {transactions.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="px-4 py-6 text-center text-gray-400">
                                            No transactions yet
                                        </td>
                                    </tr>
                                )}
                                {transactions.map((row) => {
                                    const amount = Number(row.amount_afn);
                                    return (
                                        <tr key={row.id} className="border-t">
                                            <td className="px-4 py-3">{formatDate(row.created_at)}</td>
                                            <td className="px-4 py-3 uppercase">{row.type}</td>
                                            <td className="px-4 py-3 font-mono font-semibold">
                                                {formatPrice(amount)} AFN
                                            </td>
                                            <td className="px-4 py-3 font-mono">{formatPrice(row.balance_after)} AFN</td>
                                            <td className="px-4 py-3 text-gray-500">{row.notes || '—'}</td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

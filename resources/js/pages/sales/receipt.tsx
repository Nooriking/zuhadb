import { Head } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { useEffect } from 'react';

interface SaleItem {
    id: number;
    product_name_snapshot: string | null;
    barcode_snapshot: string | null;
    quantity: number;
    sale_price_afn: string;
    line_total_afn: string;
    discount_afn: string;
}

interface Payment {
    id: number;
    amount_afn: string;
    payment_method: string;
}

interface Sale {
    id: number;
    invoice_number: string;
    sale_date: string;
    subtotal_afn: string;
    discount_afn: string;
    tax_afn: string;
    total_afn: string;
    paid_afn: string;
    due_afn: string;
    previous_due_afn?: string;
    new_sale_amount_afn?: string;
    grand_total_due_afn?: string;
    received_now_afn?: string;
    remaining_due_afn?: string;
    payment_status: 'unpaid' | 'partial' | 'paid';
    notes: string | null;
    customer_name_snapshot: string | null;
    customer_phone_snapshot: string | null;
    printed_count: number;
    customer: { id: number; name: string; phone: string | null } | null;
    creator: { name: string } | null;
    items: SaleItem[];
    payments: Payment[];
}

interface Company {
    name: string;
    phone: string;
    address: string;
    slogan: string;
    logo?: string | null;
}

interface Props {
    sale: Sale;
    company: Company;
    printedAt: string;
}

const money = (value: string | number) =>
    new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(typeof value === 'string' ? parseFloat(value) || 0 : value || 0);

const methodLabel = (method: string) => ({ cash: 'Cash', card: 'Card', bank: 'Bank', mobile: 'Mobile', other: 'Other' })[method] || method;

export default function SaleReceipt({ sale, company, printedAt }: Props) {
    useEffect(() => {
        const timer = setTimeout(() => window.print(), 500);
        return () => clearTimeout(timer);
    }, []);

    const customerName = sale.customer_name_snapshot ?? sale.customer?.name ?? 'Walk-in';
    const customerPhone = sale.customer_phone_snapshot ?? sale.customer?.phone ?? null;
    const dateOnly = new Date(sale.sale_date).toLocaleDateString('en-GB');
    const timeOnly = printedAt.split(' ')[1] || '';
    const status = sale.payment_status === 'paid' ? 'PAID' : sale.payment_status === 'partial' ? 'PARTIAL' : 'UNPAID';

    return (
        <>
            <Head title={`Invoice ${sale.invoice_number}`} />
            <div className="no-print bill-toolbar">
                <a href="/sales">
                    <ArrowLeft size={14} /> Back
                </a>
                <button type="button" onClick={() => window.print()}>
                    <Printer size={14} /> Print
                </button>
            </div>
            <main className="bill-page">
                <article className="bill">
                    <header className="bill-head">
                        {company.logo && <img loading="lazy" decoding="async" className="bill-logo" src={company.logo} alt={company.name} />}
                        <p className="bill-brand">{company.name}</p>
                        {/* {company.slogan && <p>{company.slogan}</p>} */}
                        {company.phone && <p>Tel: {company.phone}</p>}
                        {company.address && <p>{company.address}</p>}
                    </header>
                    <p className="bill-kind">Invoice</p>
                    <dl className="bill-meta">
                        <div>
                            <dt>Invoice</dt>
                            <dd>{sale.invoice_number}</dd>
                        </div>
                        <div>
                            <dt>Date</dt>
                            <dd>
                                {dateOnly} {timeOnly}
                            </dd>
                        </div>
                        <div>
                            <dt>Cashier</dt>
                            <dd>{sale.creator?.name || '—'}</dd>
                        </div>
                        <div>
                            <dt>Customer</dt>
                            <dd>{customerName}</dd>
                        </div>
                        {customerPhone && (
                            <div>
                                <dt>Phone</dt>
                                <dd>{customerPhone}</dd>
                            </div>
                        )}
                    </dl>
                    <table className="bill-items">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Qty</th>
                                <th>Price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {sale.items.map((item, index) => (
                                <tr key={item.id}>
                                    <td>
                                        {index + 1}. {item.product_name_snapshot || 'Product'}
                                    </td>
                                    <td>{item.quantity}</td>
                                    <td>{money(item.sale_price_afn)}</td>
                                    <td>{money(item.line_total_afn)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <dl className="bill-totals">
                        <div>
                            <dt>Subtotal</dt>
                            <dd>{money(sale.subtotal_afn)} AFN</dd>
                        </div>
                        {parseFloat(sale.discount_afn) > 0 && (
                            <div>
                                <dt>Discount</dt>
                                <dd>-{money(sale.discount_afn)} AFN</dd>
                            </div>
                        )}
                        {parseFloat(sale.tax_afn) > 0 && (
                            <div>
                                <dt>Tax</dt>
                                <dd>+{money(sale.tax_afn)} AFN</dd>
                            </div>
                        )}
                        <div>
                            <dt>Previous due</dt>
                            <dd>{money(sale.previous_due_afn || 0)} AFN</dd>
                        </div>
                        <div>
                            <dt>Today</dt>
                            <dd>{money(sale.new_sale_amount_afn || sale.total_afn)} AFN</dd>
                        </div>
                        <div className="bill-grand">
                            <dt>Total</dt>
                            <dd>{money(sale.grand_total_due_afn || sale.total_afn)} AFN</dd>
                        </div>
                        <div>
                            <dt>Paid</dt>
                            <dd>{money(sale.received_now_afn || sale.paid_afn)} AFN</dd>
                        </div>
                        <div className="bill-due">
                            <dt>Remaining</dt>
                            <dd>{money(sale.remaining_due_afn || sale.due_afn)} AFN</dd>
                        </div>
                    </dl>
                    {sale.payments.length > 0 && (
                        <ul className="bill-pays">
                            {sale.payments.map((payment) => (
                                <li key={payment.id}>
                                    <span>{methodLabel(payment.payment_method)}</span>
                                    <span>{money(payment.amount_afn)} AFN</span>
                                </li>
                            ))}
                        </ul>
                    )}
                    <p className="bill-status">{status}</p>
                    {sale.notes && <p className="bill-note">{sale.notes}</p>}
                    <footer className="bill-foot">
                        <p>Thank you</p>
                        {/* <p>Exchange within {company.exchange_window_days ?? 3} days with this invoice</p> */}
                        {company.address && <p>{company.address}</p>}
                        {/* {(company.support_phone || company.phone) && <p>Support {company.support_phone || company.phone}</p>} */}
                        {/* {company.manufacturer && <p>{company.manufacturer}</p>} */}
                        <p>
                            {printedAt} · Print {sale.printed_count + 1}
                        </p>
                    </footer>
                </article>
            </main>
            <style>{`
                html, body { margin: 0; background: #f4f4f5; color: #111; }
                .bill-toolbar { display: flex; justify-content: space-between; align-items: center; padding: 10px 16px; background: #fff; border-bottom: 1px solid #e5e5e5; }
                .bill-toolbar a, .bill-toolbar button { display: inline-flex; gap: 6px; align-items: center; border: 1px solid #d4d4d4; background: #fff; border-radius: 6px; padding: 6px 10px; font-size: 12px; cursor: pointer; color: inherit; text-decoration: none; }
                .bill-page { display: flex; justify-content: center; padding: 24px 12px; }
                .bill {
                    display: inline-block;
                    width: fit-content;
                    min-width: 72mm;
                    max-width: min(100%, 120mm);
                    height: auto;
                    background: #fff;
                    padding: 14px 14px 18px;
                    box-shadow: 0 8px 24px rgba(0,0,0,.08);
                    font-family: "Segoe UI", Tahoma, sans-serif;
                    font-size: 12px;
                    font-weight: 700;
                    line-height: 1.35;
                    box-sizing: border-box;
                }
                .bill * { font-weight: 700; }
                .bill-head, .bill-foot, .bill-kind, .bill-status { text-align: center; }
                .bill-logo { display: block; height: auto; max-height: 80px; width: auto; max-width: 100%; margin: 0 auto 8px; object-fit: contain; }
                .bill-brand { margin: 0; font-size: 18px; letter-spacing: .08em; }
                .bill-head p, .bill-foot p { margin: 2px 0; }
                .bill-kind { margin: 10px 0 0; font-size: 12px; letter-spacing: .18em; border-top: 1px solid #111; border-bottom: 1px solid #111; padding: 4px 0; }
                .bill-meta, .bill-totals { margin: 10px 0; }
                .bill-meta div, .bill-totals div, .bill-pays li { display: flex; justify-content: space-between; gap: 12px; margin: 3px 0; }
                .bill-meta dt, .bill-totals dt { color: #111; }
                .bill-meta dd, .bill-totals dd { margin: 0; text-align: right; white-space: nowrap; }
                .bill-items { width: 100%; border-collapse: collapse; margin-top: 8px; table-layout: auto; }
                .bill-items th { border-bottom: 1px solid #111; text-align: right; font-size: 11px; letter-spacing: .04em; padding: 4px 0; white-space: nowrap; }
                .bill-items th:first-child, .bill-items td:first-child { text-align: left; white-space: normal; word-break: break-word; }
                .bill-items td { padding: 4px 0; vertical-align: top; border-bottom: 1px dotted #ccc; white-space: nowrap; }
                .bill-grand { margin-top: 6px; padding-top: 6px; border-top: 1px solid #111; font-size: 14px; }
                .bill-due { font-size: 13px; }
                .bill-pays { list-style: none; margin: 8px 0 0; padding: 8px 0 0; border-top: 1px dotted #111; }
                .bill-status { margin: 12px 0 0; letter-spacing: .16em; }
                .bill-note { text-align: center; margin: 8px 0 0; }
                .bill-foot { margin-top: 12px; padding-top: 8px; border-top: 1px solid #111; font-size: 11px; }
                @media print {
                    html, body { background: #fff; }
                    .no-print { display: none !important; }
                    .bill-page { padding: 0; display: block; }
                    .bill { width: fit-content; min-width: 72mm; max-width: 120mm; height: auto; box-shadow: none; }
                    @page { size: auto; margin: 4mm; }
                }
            `}</style>
        </>
    );
}

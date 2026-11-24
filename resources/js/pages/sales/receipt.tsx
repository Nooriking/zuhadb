import { formatOriginalAmount } from '@/lib/original-currency';
import { Head } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { useEffect, useState } from 'react';

interface SaleItem {
    id: number;
    product_name_snapshot: string | null;
    barcode_snapshot: string | null;
    quantity: number;
    sale_price_afn: string;
    line_total_afn: string;
    discount_afn: string;
    sale_unit_amount?: string | number | null;
    sale_line_amount?: string | number | null;
    sale_currency_code?: string | null;
    display_unit_price?: number | null;
    display_line_total?: number | null;
    display_currency_code?: string | null;
    variant?: {
        volume_ml?: number | null;
        concentration?: string | null;
        product?: { name?: string | null } | null;
    } | null;
}

interface Payment {
    id: number;
    amount_afn: string;
    payment_method: string;
    display_amount?: number | null;
    display_currency_code?: string | null;
}

interface Sale {
    id: number;
    invoice_number: string;
    sale_date: string;
    subtotal_afn: string;
    discount_afn: string;
    total_afn: string;
    paid_afn: string;
    due_afn: string;
    previous_due_afn?: string;
    new_sale_amount_afn?: string;
    grand_total_due_afn?: string;
    remaining_due_afn?: string;
    payment_status: 'unpaid' | 'partial' | 'paid';
    notes: string | null;
    customer_name_snapshot: string | null;
    customer_phone_snapshot: string | null;
    printed_count: number;
    sale_currency_code?: string | null;
    sale_currency_symbol?: string | null;
    sale_currency_label?: string | null;
    sale_currency_known?: boolean;
    /** Snapshot board rate used for this sale day (1 USD → AFN). */
    usd_to_afn_rate?: string | number | null;
    aed_to_afn_rate?: string | number | null;
    usd_to_aed_rate?: string | number | null;
    rate_effective_at?: string | null;
    rate_source?: string | null;
    display_total?: number | null;
    display_subtotal?: number | null;
    display_discount?: number | null;
    display_paid?: number | null;
    display_remaining?: number | null;
    display_previous_due?: number | null;
    display_new_sale_amount?: number | null;
    display_grand_total_due?: number | null;
    customer: { id: number; name: string; phone: string | null } | null;
    creator: { name: string } | null;
    items: SaleItem[];
    payments: Payment[];
}

interface Company {
    name: string;
    phone?: string;
    phone_primary?: string;
    phone_secondary?: string;
    email?: string;
    website?: string;
    address?: string;
    slogan?: string;
    logo?: string | null;
    show_logo?: boolean;
    tax_number?: string;
    registration_no?: string;
    invoice_title?: string;
    invoice_footer?: string;
    thank_you_message?: string;
}

interface Props {
    sale: Sale;
    company: Company;
    printedAt: string;
}

const paymentStatusLabel = (status: Sale['payment_status']): string => {
    if (status === 'paid') return 'PAID';
    if (status === 'partial') return 'PARTIALLY PAID';
    return 'UNPAID';
};

const methodLabel = (method: string) =>
    ({ cash: 'Cash', card: 'Card', bank: 'Bank', mobile: 'Mobile', other: 'Other', credit: 'Credit' })[method] ||
    method;

const formatFxRate = (value: string | number | null | undefined): string | null => {
    const n = typeof value === 'string' ? parseFloat(value) : Number(value);
    if (!Number.isFinite(n) || !(n > 0)) return null;
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 4,
    }).format(n);
};

export default function SaleReceipt({ sale, company, printedAt }: Props) {
    const [logoFailed, setLogoFailed] = useState(false);

    useEffect(() => {
        const timer = setTimeout(() => window.print(), 500);
        return () => clearTimeout(timer);
    }, []);

    const customerName = sale.customer_name_snapshot ?? sale.customer?.name ?? 'Walk-in';
    const customerPhone = sale.customer_phone_snapshot ?? sale.customer?.phone ?? null;
    const dateOnly = new Date(sale.sale_date).toLocaleDateString('en-GB');
    const timeOnly = printedAt.split(' ')[1] || '';
    const currencyCode =
        sale.sale_currency_known === false
            ? null
            : sale.sale_currency_code || sale.items[0]?.display_currency_code || 'AFN';

    const money = (value: string | number | null | undefined, code: string | null | undefined = currencyCode) =>
        formatOriginalAmount(value, code, 2);

    const subtotal = sale.display_subtotal ?? parseFloat(sale.subtotal_afn || '0');
    const discount = sale.display_discount ?? parseFloat(sale.discount_afn || '0');
    const previousDue = sale.display_previous_due ?? parseFloat(sale.previous_due_afn || '0');
    const thisSale = sale.display_new_sale_amount ?? sale.display_total ?? parseFloat(sale.total_afn || '0');
    const grandTotal =
        sale.display_grand_total_due ??
        parseFloat(sale.grand_total_due_afn || sale.total_afn || '0');
    const paid = sale.display_paid ?? parseFloat(sale.paid_afn || '0');
    const remaining = sale.display_remaining ?? parseFloat(sale.remaining_due_afn || sale.due_afn || '0');

    const showLogo = company.show_logo !== false && !!company.logo && !logoFailed;
    const invoiceTitle = company.invoice_title || 'Invoice';
    const thankYou = (company.thank_you_message || '').trim();
    const phoneLine = (company.phone || company.phone_primary || '').trim();
    const address = (company.address || '').trim();
    const email = (company.email || '').trim();
    const website = (company.website || '').trim();
    const invoiceFooter = (company.invoice_footer || '').trim();

    // Day-of-sale USD board rate (snapshot on the sale — not today's live board).
    const usdToAfn = formatFxRate(sale.usd_to_afn_rate);
    const rateDay = sale.rate_effective_at
        ? new Date(sale.rate_effective_at).toLocaleDateString('en-GB')
        : dateOnly;

    const itemLabel = (item: SaleItem) => {
        const name = item.product_name_snapshot || item.variant?.product?.name || 'Product';
        const size = [
            item.variant?.volume_ml ? `${item.variant.volume_ml}ml` : null,
            item.variant?.concentration ? String(item.variant.concentration).toUpperCase() : null,
        ]
            .filter(Boolean)
            .join(' · ');
        return size && !name.includes(String(item.variant?.volume_ml ?? '')) ? `${name} — ${size}` : name;
    };

    return (
        <>
            <Head title={`${invoiceTitle} ${sale.invoice_number}`} />
            <div className="no-print bill-toolbar">
                <a href={`/sales/${sale.id}`}>
                    <ArrowLeft size={14} /> Back
                </a>
                <button type="button" onClick={() => window.print()}>
                    <Printer size={14} /> Print
                </button>
            </div>
            <main className="bill-page">
                <article className="bill" dir="auto">
                    <header className="bill-head">
                        {showLogo && (
                            <img
                                className="bill-logo"
                                src={company.logo!}
                                alt={company.name}
                                onError={() => setLogoFailed(true)}
                            />
                        )}
                        <p className="bill-brand">{company.name}</p>
                    </header>

                    <p className="bill-kind">{invoiceTitle}</p>

                    <dl className="bill-meta">
                        <div>
                            <dt>Invoice</dt>
                            <dd className="ltr">{sale.invoice_number}</dd>
                        </div>
                        <div>
                            <dt>Date</dt>
                            <dd className="ltr">
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
                                <dd className="ltr">{customerPhone}</dd>
                            </div>
                        )}
                        <div>
                            <dt>Currency</dt>
                            <dd className="ltr">{sale.sale_currency_label || currencyCode || 'AFN'}</dd>
                        </div>
                    </dl>

                    {usdToAfn && (
                        <div className="bill-fx">
                            <p className="bill-fx-title">Exchange Rate (Sale Day)</p>
                            <p className="bill-fx-rate ltr">1 USD = {usdToAfn} AFN</p>
                            <p className="bill-fx-day ltr">Rate date: {rateDay}</p>
                        </div>
                    )}

                    <table className="bill-items">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Line Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {sale.items.map((item) => {
                                const unit =
                                    item.display_unit_price ?? item.sale_unit_amount ?? item.sale_price_afn;
                                const line =
                                    item.display_line_total ?? item.sale_line_amount ?? item.line_total_afn;
                                const lineCode = item.display_currency_code || currencyCode;
                                return (
                                    <tr key={item.id}>
                                        <td>{itemLabel(item)}</td>
                                        <td className="ltr num">{item.quantity}</td>
                                        <td className="ltr num">{money(unit, lineCode)}</td>
                                        <td className="ltr num">{money(line, lineCode)}</td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>

                    <dl className="bill-totals">
                        <div>
                            <dt>Subtotal</dt>
                            <dd className="ltr num">{money(subtotal)}</dd>
                        </div>
                        {discount > 0 && (
                            <div>
                                <dt>Discount</dt>
                                <dd className="ltr num">-{money(discount)}</dd>
                            </div>
                        )}
                        {previousDue > 0 && (
                            <>
                                <div>
                                    <dt>Previous due</dt>
                                    <dd className="ltr num">{money(previousDue)}</dd>
                                </div>
                                <div>
                                    <dt>This sale</dt>
                                    <dd className="ltr num">{money(thisSale)}</dd>
                                </div>
                            </>
                        )}
                        <div className="bill-grand">
                            <dt>Grand Total</dt>
                            <dd className="ltr num">{money(grandTotal)}</dd>
                        </div>
                        <div>
                            <dt>Paid</dt>
                            <dd className="ltr num">{money(paid)}</dd>
                        </div>
                        <div className="bill-due">
                            <dt>Remaining</dt>
                            <dd className="ltr num">{money(remaining)}</dd>
                        </div>
                    </dl>

                    {sale.payments.length > 0 && (
                        <p className="bill-methods ltr">
                            Payment:{' '}
                            {sale.payments.map((p) => methodLabel(p.payment_method)).join(', ')}
                        </p>
                    )}

                    <p className="bill-status">{paymentStatusLabel(sale.payment_status)}</p>
                    {sale.notes && <p className="bill-note">{sale.notes}</p>}

                    <footer className="bill-foot">
                        {thankYou && <p className="bill-thanks">{thankYou}</p>}
                        {invoiceFooter && <p>{invoiceFooter}</p>}
                        <div className="bill-contact">
                            {phoneLine && <p className="ltr">Tel: {phoneLine}</p>}
                            {address && <p>{address}</p>}
                            {email && <p className="ltr">{email}</p>}
                            {website && <p className="ltr">{website}</p>}
                            {company.tax_number && <p className="ltr">TIN: {company.tax_number}</p>}
                            {company.registration_no && <p className="ltr">Reg: {company.registration_no}</p>}
                        </div>
                        <p className="bill-print-meta ltr">
                            {printedAt} · Print {sale.printed_count + 1}
                        </p>
                    </footer>
                </article>
            </main>
            <style>{`
                html, body, #app { margin: 0; padding: 0; background: #f4f4f5; color: #111; }
                .bill-toolbar { display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: #fff; border-bottom: 1px solid #e5e5e5; }
                .bill-toolbar a, .bill-toolbar button { display: inline-flex; gap: 6px; align-items: center; border: 1px solid #d4d4d4; background: #fff; border-radius: 6px; padding: 6px 10px; font-size: 12px; cursor: pointer; color: inherit; text-decoration: none; }
                .bill-page { display: flex; justify-content: center; padding: 8px 8px 12px; margin: 0; }
                .bill {
                    display: inline-block;
                    width: fit-content;
                    min-width: 72mm;
                    max-width: min(100%, 120mm);
                    height: auto;
                    background: #fff;
                    padding: 10px 12px 14px;
                    box-shadow: 0 8px 24px rgba(0,0,0,.08);
                    font-family: "Segoe UI", Tahoma, "Noto Naskh Arabic", sans-serif;
                    font-size: 12px;
                    font-weight: 600;
                    line-height: 1.35;
                    box-sizing: border-box;
                }
                .bill-head, .bill-foot, .bill-kind, .bill-status, .bill-note, .bill-methods { text-align: center; }
                .bill-logo { display: block; height: auto; max-height: 56px; width: auto; max-width: 72mm; margin: 0 auto 6px; object-fit: contain; }
                .bill-brand { margin: 0 0 2px; font-size: 18px; letter-spacing: .06em; font-weight: 700; }
                .bill-head { margin-bottom: 2px; }
                .bill-head p, .bill-foot p { margin: 2px 0; }
                .bill-kind { margin: 8px 0 0; font-size: 12px; letter-spacing: .16em; text-transform: uppercase; border-top: 1px solid #111; border-bottom: 1px solid #111; padding: 4px 0; }
                .bill-meta, .bill-totals { margin: 8px 0; }
                .bill-meta div, .bill-totals div { display: flex; justify-content: space-between; gap: 12px; margin: 3px 0; }
                .bill-meta dt, .bill-totals dt { color: #111; }
                .bill-meta dd, .bill-totals dd { margin: 0; text-align: right; }
                .bill-fx {
                    margin: 8px 0;
                    padding: 6px 0;
                    border-top: 1px dashed #111;
                    border-bottom: 1px dashed #111;
                    text-align: center;
                }
                .bill-fx-title {
                    margin: 0 0 2px;
                    font-size: 10px;
                    letter-spacing: .08em;
                    text-transform: uppercase;
                    font-weight: 700;
                }
                .bill-fx-rate {
                    margin: 0;
                    font-size: 13px;
                    font-weight: 700;
                    font-variant-numeric: tabular-nums;
                }
                .bill-fx-day {
                    margin: 2px 0 0;
                    font-size: 10px;
                    font-weight: 500;
                    color: #333;
                }
                .ltr { direction: ltr; unicode-bidi: isolate; }
                .num { white-space: nowrap; font-variant-numeric: tabular-nums; }
                .bill-items { width: 100%; border-collapse: collapse; margin-top: 6px; table-layout: fixed; }
                .bill-items th { border-bottom: 1px solid #111; text-align: right; font-size: 10px; letter-spacing: .03em; padding: 4px 2px; white-space: nowrap; }
                .bill-items th:first-child, .bill-items td:first-child { text-align: left; width: 42%; white-space: normal; word-break: break-word; overflow-wrap: anywhere; }
                .bill-items td { padding: 4px 2px; vertical-align: top; border-bottom: 1px dotted #ccc; text-align: right; }
                .bill-items tr { break-inside: avoid; page-break-inside: avoid; }
                .bill-grand { margin-top: 6px; padding-top: 6px; border-top: 1px solid #111; font-size: 14px; font-weight: 700; }
                .bill-due { font-size: 13px; }
                .bill-methods { margin: 6px 0 0; font-size: 11px; font-weight: 500; color: #333; }
                .bill-status { margin: 10px 0 0; letter-spacing: .14em; font-weight: 700; }
                .bill-note { margin: 8px 0 0; font-weight: 500; }
                .bill-foot { margin-top: 12px; padding-top: 8px; border-top: 1px solid #111; font-size: 11px; font-weight: 500; }
                .bill-thanks { margin: 0 0 6px; font-weight: 600; }
                .bill-contact { margin: 6px 0; line-height: 1.4; }
                .bill-contact p { margin: 2px 0; color: #222; }
                .bill-print-meta { margin-top: 8px; color: #555; font-size: 10px; font-weight: 500; }
                .bill-totals, .bill-foot, .bill-status { break-inside: avoid; page-break-inside: avoid; }
                @media print {
                    html, body, #app { margin: 0; padding: 0; background: #fff !important; }
                    .no-print { display: none !important; }
                    .bill-page { padding: 0; margin: 0; display: block; }
                    .bill { width: fit-content; min-width: 72mm; max-width: 120mm; height: auto; box-shadow: none; padding: 0; }
                    @page { size: auto; margin: 2mm; }
                }
            `}</style>
        </>
    );
}

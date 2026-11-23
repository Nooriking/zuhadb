import { Head } from '@inertiajs/react';
import { Printer, X } from 'lucide-react';
import { useEffect, useState } from 'react';

interface SaleItem {
    id: number;
    product_name_snapshot: string | null;
    quantity: number;
    sale_price_afn: string;
    line_total_afn: string;
    variant?: { product?: { name?: string; brand?: { name?: string } | null } | null } | null;
}

interface Receipt {
    id: number;
    receipt_number: string;
    receipt_type: 'sale_invoice' | 'payment_receipt';
    amount_afn: string;
    amount_currency?: string | null;
    currency_code?: string | null;
    exchange_rate_to_afn?: string | null;
    remaining_due_afn: string;
    created_at: string;
    customer: { id: number; name: string; phone: string | null; current_due_afn: string } | null;
    sale: {
        id: number;
        invoice_number: string;
        sale_date: string;
        total_afn: string;
        paid_afn: string;
        previous_due_afn?: string;
        grand_total_due_afn?: string;
        remaining_due_afn?: string;
        items?: SaleItem[];
    } | null;
    sale_payment: { payment_method?: string; amount_afn: string } | null;
    creator: { id: number; name: string } | null;
}

interface Company {
    name: string;
    phone?: string;
    email?: string;
    website?: string;
    address?: string;
    slogan?: string;
    logo?: string | null;
    show_logo?: boolean;
    invoice_title?: string;
    invoice_footer?: string;
    thank_you_message?: string;
    tax_number?: string;
    registration_no?: string;
}

interface Props {
    receipt: Receipt;
    company: Company;
    printedAt: string;
}

const money = (value: string | number) =>
    new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(
        typeof value === 'string' ? parseFloat(value) || 0 : value || 0,
    );

const methodLabel = (method?: string) =>
    ({ cash: 'Cash', card: 'Card', bank: 'Bank', mobile: 'Mobile', other: 'Other' })[method || ''] ||
    method ||
    'Cash';

export default function ReceiptPrint({ receipt, company, printedAt }: Props) {
    const [logoFailed, setLogoFailed] = useState(false);

    useEffect(() => {
        const timer = setTimeout(() => window.print(), 500);
        return () => clearTimeout(timer);
    }, []);

    const isInvoice = receipt.receipt_type === 'sale_invoice';
    const amount = parseFloat(receipt.amount_afn || '0');
    const currencyCode = (receipt.currency_code || 'AFN').toUpperCase();
    const amountCurrency = parseFloat(receipt.amount_currency || String(amount));
    const showFx = currencyCode !== 'AFN';
    const formatPaid = (afn: number) =>
        showFx ? `${money(amountCurrency)} ${currencyCode} (${money(afn)} AFN)` : `${money(afn)} AFN`;
    const remaining = parseFloat(receipt.remaining_due_afn || '0');
    const saleTotal = parseFloat(receipt.sale?.total_afn || '0');
    const previousDue = isInvoice
        ? parseFloat(receipt.sale?.previous_due_afn || '0')
        : amount + remaining;
    const grand = isInvoice ? previousDue + (saleTotal || amount) : previousDue;
    const paid = isInvoice ? parseFloat(receipt.sale?.paid_afn || String(amount)) : amount;
    const items = receipt.sale?.items ?? [];

    const showLogo = company.show_logo !== false && !!company.logo && !logoFailed;
    const thankYou = (company.thank_you_message || '').trim();
    const phoneLine = (company.phone || '').trim();
    const address = (company.address || '').trim();
    const email = (company.email || '').trim();
    const website = (company.website || '').trim();
    const invoiceFooter = (company.invoice_footer || '').trim();
    const title = isInvoice ? company.invoice_title || 'Invoice' : 'Payment receipt';

    return (
        <>
            <Head title={`${title} ${receipt.receipt_number}`} />
            <div className="no-print bill-toolbar">
                <button type="button" onClick={() => window.close()}>
                    <X size={14} /> Close
                </button>
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
                    <p className="bill-kind">{title}</p>
                    <dl className="bill-meta">
                        <div>
                            <dt>Number</dt>
                            <dd className="ltr">{receipt.receipt_number}</dd>
                        </div>
                        <div>
                            <dt>Date</dt>
                            <dd className="ltr">{new Date(receipt.created_at).toLocaleString('en-GB')}</dd>
                        </div>
                        <div>
                            <dt>Cashier</dt>
                            <dd>{receipt.creator?.name || '—'}</dd>
                        </div>
                        <div>
                            <dt>Customer</dt>
                            <dd>{receipt.customer?.name || 'Walk-in'}</dd>
                        </div>
                        {receipt.customer?.phone && (
                            <div>
                                <dt>Phone</dt>
                                <dd className="ltr">{receipt.customer.phone}</dd>
                            </div>
                        )}
                        {receipt.sale && (
                            <div>
                                <dt>Sale invoice</dt>
                                <dd className="ltr">{receipt.sale.invoice_number}</dd>
                            </div>
                        )}
                    </dl>
                    {items.length > 0 && (
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
                                {items.map((item, index) => (
                                    <tr key={item.id}>
                                        <td>
                                            {index + 1}.{' '}
                                            {item.product_name_snapshot ||
                                                item.variant?.product?.name ||
                                                'Product'}
                                        </td>
                                        <td className="ltr num">{item.quantity}</td>
                                        <td className="ltr num">{money(item.sale_price_afn)}</td>
                                        <td className="ltr num">{money(item.line_total_afn)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                    <dl className="bill-totals">
                        {isInvoice && previousDue > 0 && (
                            <>
                                <div>
                                    <dt>Previous due</dt>
                                    <dd className="ltr num">{money(previousDue)} AFN</dd>
                                </div>
                                <div>
                                    <dt>This sale</dt>
                                    <dd className="ltr num">{money(saleTotal || amount)} AFN</dd>
                                </div>
                            </>
                        )}
                        {isInvoice && (
                            <div className="bill-grand">
                                <dt>Grand Total</dt>
                                <dd className="ltr num">
                                    {money(parseFloat(receipt.sale?.grand_total_due_afn || '') || grand)}{' '}
                                    AFN
                                </dd>
                            </div>
                        )}
                        <div className={isInvoice ? '' : 'bill-grand'}>
                            <dt>{isInvoice ? 'Paid' : 'Paid now'}</dt>
                            <dd className="ltr num">{formatPaid(paid)}</dd>
                        </div>
                        {!isInvoice && (
                            <div>
                                <dt>Method</dt>
                                <dd>{methodLabel(receipt.sale_payment?.payment_method)}</dd>
                            </div>
                        )}
                        <div className="bill-due">
                            <dt>Remaining</dt>
                            <dd className="ltr num">{money(remaining)} AFN</dd>
                        </div>
                    </dl>
                    <p className="bill-status">{remaining <= 0 ? 'PAID' : 'PARTIALLY PAID'}</p>
                    <footer className="bill-foot">
                        {thankYou && <p className="bill-thanks">{thankYou}</p>}
                        {invoiceFooter && <p>{invoiceFooter}</p>}
                        <div className="bill-contact">
                            {phoneLine && <p className="ltr">Tel: {phoneLine}</p>}
                            {address && <p>{address}</p>}
                            {email && <p className="ltr">{email}</p>}
                            {website && <p className="ltr">{website}</p>}
                            {company.tax_number && <p className="ltr">TIN: {company.tax_number}</p>}
                            {company.registration_no && (
                                <p className="ltr">Reg: {company.registration_no}</p>
                            )}
                        </div>
                        <p className="bill-print-meta ltr">{printedAt}</p>
                    </footer>
                </article>
            </main>
            <style>{`
                html, body, #app { margin: 0; padding: 0; background: #f4f4f5; color: #111; }
                .bill-toolbar { display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: #fff; border-bottom: 1px solid #e5e5e5; }
                .bill-toolbar button { display: inline-flex; gap: 6px; align-items: center; border: 1px solid #d4d4d4; background: #fff; border-radius: 6px; padding: 6px 10px; font-size: 12px; cursor: pointer; }
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
                .bill-head, .bill-foot, .bill-kind, .bill-status { text-align: center; }
                .bill-logo { display: block; height: auto; max-height: 56px; width: auto; max-width: 72mm; margin: 0 auto 6px; object-fit: contain; }
                .bill-brand { margin: 0 0 2px; font-size: 18px; letter-spacing: .06em; font-weight: 700; }
                .bill-head p, .bill-foot p { margin: 2px 0; }
                .bill-kind { margin: 8px 0 0; font-size: 12px; letter-spacing: .16em; text-transform: uppercase; border-top: 1px solid #111; border-bottom: 1px solid #111; padding: 4px 0; }
                .bill-meta, .bill-totals { margin: 8px 0; }
                .bill-meta div, .bill-totals div { display: flex; justify-content: space-between; gap: 12px; margin: 3px 0; }
                .bill-meta dt, .bill-totals dt { color: #111; }
                .bill-meta dd, .bill-totals dd { margin: 0; text-align: right; }
                .ltr { direction: ltr; unicode-bidi: isolate; }
                .num { white-space: nowrap; font-variant-numeric: tabular-nums; }
                .bill-items { width: 100%; border-collapse: collapse; margin-top: 6px; table-layout: fixed; }
                .bill-items th { border-bottom: 1px solid #111; text-align: right; font-size: 10px; padding: 4px 2px; white-space: nowrap; }
                .bill-items th:first-child, .bill-items td:first-child { text-align: left; width: 42%; white-space: normal; word-break: break-word; overflow-wrap: anywhere; }
                .bill-items td { padding: 4px 2px; vertical-align: top; border-bottom: 1px dotted #ccc; text-align: right; }
                .bill-items tr { break-inside: avoid; page-break-inside: avoid; }
                .bill-grand { margin-top: 6px; padding-top: 6px; border-top: 1px solid #111; font-size: 14px; }
                .bill-due { font-size: 13px; }
                .bill-status { margin: 10px 0 0; letter-spacing: .14em; font-weight: 700; }
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

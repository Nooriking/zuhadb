import { Head } from '@inertiajs/react';
import { Printer, X } from 'lucide-react';
import { useEffect } from 'react';

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
    phone: string;
    address: string;
    slogan: string;
    logo?: string | null;
}

interface Props {
    receipt: Receipt;
    company: Company;
    printedAt: string;
}

const money = (value: string | number) =>
    new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(typeof value === 'string' ? parseFloat(value) || 0 : value || 0);

const methodLabel = (method?: string) =>
    ({ cash: 'Cash', card: 'Card', bank: 'Bank', mobile: 'Mobile', other: 'Other' })[method || ''] || method || 'Cash';

export default function ReceiptPrint({ receipt, company, printedAt }: Props) {
    useEffect(() => {
        const timer = setTimeout(() => window.print(), 500);
        return () => clearTimeout(timer);
    }, []);

    const isInvoice = receipt.receipt_type === 'sale_invoice';
    const amount = parseFloat(receipt.amount_afn || '0');
    const remaining = parseFloat(receipt.remaining_due_afn || '0');
    const saleTotal = parseFloat(receipt.sale?.total_afn || '0');
    const previousDue = isInvoice
        ? parseFloat(receipt.sale?.previous_due_afn || '0')
        : amount + remaining;
    const grand = isInvoice ? previousDue + (saleTotal || amount) : previousDue;
    const paid = isInvoice ? parseFloat(receipt.sale?.paid_afn || String(amount)) : amount;
    const items = receipt.sale?.items ?? [];

    return (
        <>
            <Head title={`${isInvoice ? 'Invoice' : 'Receipt'} ${receipt.receipt_number}`} />
            <div className="no-print bill-toolbar">
                <button type="button" onClick={() => window.close()}>
                    <X size={14} /> Close
                </button>
                <button type="button" onClick={() => window.print()}>
                    <Printer size={14} /> Print
                </button>
            </div>
            <main className="bill-page">
                <article className="bill">
                    <header className="bill-head">
                        {company.logo && <img className="bill-logo" src={company.logo} alt={company.name} />}
                        <p className="bill-brand">{company.name}</p>
                        {company.slogan && <p>{company.slogan}</p>}
                        {company.phone && <p>Tel: {company.phone}</p>}
                        {company.address && <p>{company.address}</p>}
                    </header>
                    <p className="bill-kind">{isInvoice ? 'Invoice' : 'Payment receipt'}</p>
                    <dl className="bill-meta">
                        <div><dt>Number</dt><dd>{receipt.receipt_number}</dd></div>
                        <div><dt>Date</dt><dd>{new Date(receipt.created_at).toLocaleString('en-GB')}</dd></div>
                        <div><dt>Cashier</dt><dd>{receipt.creator?.name || '—'}</dd></div>
                        <div><dt>Customer</dt><dd>{receipt.customer?.name || 'Walk-in'}</dd></div>
                        {receipt.customer?.phone && <div><dt>Phone</dt><dd>{receipt.customer.phone}</dd></div>}
                        {receipt.sale && <div><dt>Sale invoice</dt><dd>{receipt.sale.invoice_number}</dd></div>}
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
                                        <td>{index + 1}. {item.product_name_snapshot || item.variant?.product?.name || 'Product'}</td>
                                        <td>{item.quantity}</td>
                                        <td>{money(item.sale_price_afn)}</td>
                                        <td>{money(item.line_total_afn)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                    <dl className="bill-totals">
                        <div><dt>Previous due</dt><dd>{money(previousDue)} AFN</dd></div>
                        {isInvoice && <div><dt>This sale</dt><dd>{money(saleTotal || amount)} AFN</dd></div>}
                        {isInvoice && <div className="bill-grand"><dt>Total</dt><dd>{money(grand)} AFN</dd></div>}
                        <div className={isInvoice ? '' : 'bill-grand'}><dt>{isInvoice ? 'Paid' : 'Paid now'}</dt><dd>{money(paid)} AFN</dd></div>
                        {!isInvoice && (
                            <div><dt>Method</dt><dd>{methodLabel(receipt.sale_payment?.payment_method)}</dd></div>
                        )}
                        <div className="bill-due"><dt>Remaining</dt><dd>{money(remaining)} AFN</dd></div>
                    </dl>
                    <p className="bill-status">{remaining <= 0 ? 'PAID' : 'PARTIAL'}</p>
                    <footer className="bill-foot">
                        <p>Thank you</p>
                        {company.address && <p>{company.address}</p>}
                        <p>{printedAt}</p>
                    </footer>
                </article>
            </main>
            <style>{`
                html, body { margin: 0; background: #f4f4f5; color: #111; }
                .bill-toolbar { display: flex; justify-content: space-between; align-items: center; padding: 10px 16px; background: #fff; border-bottom: 1px solid #e5e5e5; }
                .bill-toolbar button { display: inline-flex; gap: 6px; align-items: center; border: 1px solid #d4d4d4; background: #fff; border-radius: 6px; padding: 6px 10px; font-size: 12px; cursor: pointer; }
                .bill-page { display: flex; justify-content: center; padding: 24px 12px; }
                .bill { width: 80mm; background: #fff; padding: 14px 12px 18px; box-shadow: 0 8px 24px rgba(0,0,0,.08); font-family: "Segoe UI", Tahoma, sans-serif; font-size: 12px; }
                .bill-head, .bill-foot, .bill-kind, .bill-status { text-align: center; }
                .bill-logo { display: block; height: 72px; width: auto; max-width: 100%; margin: 0 auto 8px; object-fit: contain; }
                .bill-brand { margin: 0; font-size: 18px; letter-spacing: .08em; font-weight: 700; }
                .bill-head p, .bill-foot p { margin: 2px 0; }
                .bill-kind { margin: 10px 0 0; font-size: 11px; letter-spacing: .22em; font-weight: 700; border-top: 1px solid #111; border-bottom: 1px solid #111; padding: 4px 0; }
                .bill-meta, .bill-totals { margin: 10px 0; }
                .bill-meta div, .bill-totals div { display: flex; justify-content: space-between; gap: 12px; margin: 3px 0; }
                .bill-meta dt, .bill-totals dt { color: #444; }
                .bill-meta dd, .bill-totals dd { margin: 0; font-weight: 600; text-align: right; }
                .bill-items { width: 100%; border-collapse: collapse; margin-top: 8px; }
                .bill-items th { border-bottom: 1px solid #111; text-align: right; font-size: 10px; padding: 4px 0; }
                .bill-items th:first-child, .bill-items td:first-child { text-align: left; }
                .bill-items td { padding: 4px 0; vertical-align: top; border-bottom: 1px dotted #ccc; }
                .bill-grand { margin-top: 6px; padding-top: 6px; border-top: 1px solid #111; font-size: 14px; }
                .bill-due { font-weight: 700; }
                .bill-status { margin: 12px 0 0; font-weight: 700; letter-spacing: .16em; }
                .bill-foot { margin-top: 12px; padding-top: 8px; border-top: 1px solid #111; font-size: 11px; }
                @media print {
                    html, body { background: #fff; }
                    .no-print { display: none !important; }
                    .bill-page { padding: 0; }
                    .bill { width: 80mm; box-shadow: none; }
                    @page { size: 80mm auto; margin: 4mm; }
                }
            `}</style>
        </>
    );
}

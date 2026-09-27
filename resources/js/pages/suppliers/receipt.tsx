import { Head } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { useEffect } from 'react';

interface Props {
    kind: 'credit' | 'payment';
    company: { name: string; phone: string; address: string; slogan: string; logo?: string | null };
    supplier: { id: number; name: string; phone: string | null };
    printedAt: string;
    receipt: {
        number: string;
        date: string | null;
        title: string;
        method?: string;
        lines: { name: string; quantity: number | null; amount: number }[];
        amount: number;
        paid: number;
        remaining: number;
        balance: number;
        status: string;
        notes?: string | null;
    };
}

const money = (value: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(value || 0);

export default function SupplierReceipt({ company, supplier, printedAt, receipt }: Props) {
    useEffect(() => {
        const timer = setTimeout(() => window.print(), 500);
        return () => clearTimeout(timer);
    }, []);

    return (
        <>
            <Head title={`${receipt.title} ${receipt.number}`} />
            <div className="no-print bill-toolbar">
                <a href="/suppliers">
                    <ArrowLeft size={14} /> Suppliers
                </a>
                <a href={`/suppliers/${supplier.id}`}>Supplier</a>
                <button type="button" onClick={() => window.print()}>
                    <Printer size={14} /> Print
                </button>
            </div>
            <main className="bill-page">
                <article className="bill">
                    <header className="bill-head">
                        {company.logo && <img loading="lazy" decoding="async" className="bill-logo" src={company.logo} alt={company.name} />}
                        <p className="bill-brand">{company.name}</p>
                        {company.phone && <p>Tel: {company.phone}</p>}
                        {company.address && <p>{company.address}</p>}
                    </header>
                    <p className="bill-kind">{receipt.title}</p>
                    <dl className="bill-meta">
                        <div><dt>Number</dt><dd>{receipt.number}</dd></div>
                        <div><dt>Date</dt><dd>{receipt.date || '—'}</dd></div>
                        <div><dt>Supplier</dt><dd>{supplier.name}</dd></div>
                        {supplier.phone && <div><dt>Phone</dt><dd>{supplier.phone}</dd></div>}
                        {receipt.method && <div><dt>Method</dt><dd>{receipt.method}</dd></div>}
                    </dl>
                    <table className="bill-items">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            {receipt.lines.map((line, index) => (
                                <tr key={`${line.name}-${index}`}>
                                    <td>{line.name}{line.quantity ? ` × ${line.quantity}` : ''}</td>
                                    <td>{money(line.amount)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <dl className="bill-totals">
                        <div><dt>Amount</dt><dd>{money(receipt.amount)} AFN</dd></div>
                        <div><dt>Paid</dt><dd>{money(receipt.paid)} AFN</dd></div>
                        <div className="bill-due"><dt>Due on this bill</dt><dd>{money(receipt.remaining)} AFN</dd></div>
                        <div className="bill-grand"><dt>Supplier balance</dt><dd>{money(receipt.balance)} AFN</dd></div>
                    </dl>
                    <p className="bill-status">{receipt.status}</p>
                    {receipt.notes && <p className="bill-note">{receipt.notes}</p>}
                    <footer className="bill-foot">
                        <p>{printedAt}</p>
                        {company.address && <p>{company.address}</p>}
                    </footer>
                </article>
            </main>
            <style>{`
                html, body { margin: 0; background: #f4f4f5; color: #111; }
                .bill-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 10px 16px; background: #fff; border-bottom: 1px solid #e5e5e5; }
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
                .bill-kind { margin: 10px 0 0; font-size: 13px; border-top: 1px solid #111; border-bottom: 1px solid #111; padding: 4px 0; }
                .bill-meta, .bill-totals { margin: 10px 0; }
                .bill-meta div, .bill-totals div { display: flex; justify-content: space-between; gap: 12px; margin: 3px 0; }
                .bill-meta dt, .bill-totals dt { color: #111; }
                .bill-meta dd, .bill-totals dd { margin: 0; text-align: right; white-space: nowrap; }
                .bill-items { width: 100%; border-collapse: collapse; margin-top: 8px; table-layout: auto; }
                .bill-items th { border-bottom: 1px solid #111; text-align: right; font-size: 11px; padding: 4px 0; white-space: nowrap; }
                .bill-items th:first-child, .bill-items td:first-child { text-align: left; white-space: normal; word-break: break-word; }
                .bill-items td { padding: 4px 0; border-bottom: 1px dotted #ccc; white-space: nowrap; }
                .bill-grand { margin-top: 6px; padding-top: 6px; border-top: 1px solid #111; font-size: 14px; }
                .bill-due { font-size: 13px; }
                .bill-status { margin: 12px 0 0; }
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

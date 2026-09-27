import{r as m,j as i}from"./react-vendor-D89vF4rI.js";import{L as j}from"./inertia-CXinea7p.js";import{X as g,V as f}from"./icons-lvCR970c.js";import"./charts-DewAyPor.js";const d=e=>new Intl.NumberFormat("en-US",{maximumFractionDigits:0}).format(typeof e=="string"?parseFloat(e)||0:e||0),u=e=>({cash:"Cash",card:"Card",bank:"Bank",mobile:"Mobile",other:"Other"})[e||""]||e||"Cash";function k({receipt:e,company:l,printedAt:x}){m.useEffect(()=>{const t=setTimeout(()=>window.print(),500);return()=>clearTimeout(t)},[]);const s=e.receipt_type==="sale_invoice",n=parseFloat(e.amount_afn||"0"),a=parseFloat(e.remaining_due_afn||"0"),o=parseFloat(e.sale?.total_afn||"0"),r=s?parseFloat(e.sale?.previous_due_afn||"0"):n+a,h=s?r+(o||n):r,p=s?parseFloat(e.sale?.paid_afn||String(n)):n,c=e.sale?.items??[];return i.jsxs(i.Fragment,{children:[i.jsx(j,{title:`${s?"Invoice":"Receipt"} ${e.receipt_number}`}),i.jsxs("div",{className:"no-print bill-toolbar",children:[i.jsxs("button",{type:"button",onClick:()=>window.close(),children:[i.jsx(g,{size:14})," Close"]}),i.jsxs("button",{type:"button",onClick:()=>window.print(),children:[i.jsx(f,{size:14})," Print"]})]}),i.jsx("main",{className:"bill-page",children:i.jsxs("article",{className:"bill",children:[i.jsxs("header",{className:"bill-head",children:[l.logo&&i.jsx("img",{loading:"lazy",decoding:"async",className:"bill-logo",src:l.logo,alt:l.name}),i.jsx("p",{className:"bill-brand",children:l.name}),l.slogan&&i.jsx("p",{children:l.slogan}),l.phone&&i.jsxs("p",{children:["Tel: ",l.phone]}),l.address&&i.jsx("p",{children:l.address})]}),i.jsx("p",{className:"bill-kind",children:s?"Invoice":"Payment receipt"}),i.jsxs("dl",{className:"bill-meta",children:[i.jsxs("div",{children:[i.jsx("dt",{children:"Number"}),i.jsx("dd",{children:e.receipt_number})]}),i.jsxs("div",{children:[i.jsx("dt",{children:"Date"}),i.jsx("dd",{children:new Date(e.created_at).toLocaleString("en-GB")})]}),i.jsxs("div",{children:[i.jsx("dt",{children:"Cashier"}),i.jsx("dd",{children:e.creator?.name||"—"})]}),i.jsxs("div",{children:[i.jsx("dt",{children:"Customer"}),i.jsx("dd",{children:e.customer?.name||"Walk-in"})]}),e.customer?.phone&&i.jsxs("div",{children:[i.jsx("dt",{children:"Phone"}),i.jsx("dd",{children:e.customer.phone})]}),e.sale&&i.jsxs("div",{children:[i.jsx("dt",{children:"Sale invoice"}),i.jsx("dd",{children:e.sale.invoice_number})]})]}),c.length>0&&i.jsxs("table",{className:"bill-items",children:[i.jsx("thead",{children:i.jsxs("tr",{children:[i.jsx("th",{children:"Item"}),i.jsx("th",{children:"Qty"}),i.jsx("th",{children:"Price"}),i.jsx("th",{children:"Total"})]})}),i.jsx("tbody",{children:c.map((t,b)=>i.jsxs("tr",{children:[i.jsxs("td",{children:[b+1,". ",t.product_name_snapshot||t.variant?.product?.name||"Product"]}),i.jsx("td",{children:t.quantity}),i.jsx("td",{children:d(t.sale_price_afn)}),i.jsx("td",{children:d(t.line_total_afn)})]},t.id))})]}),i.jsxs("dl",{className:"bill-totals",children:[i.jsxs("div",{children:[i.jsx("dt",{children:"Previous due"}),i.jsxs("dd",{children:[d(r)," AFN"]})]}),s&&i.jsxs("div",{children:[i.jsx("dt",{children:"This sale"}),i.jsxs("dd",{children:[d(o||n)," AFN"]})]}),s&&i.jsxs("div",{className:"bill-grand",children:[i.jsx("dt",{children:"Total"}),i.jsxs("dd",{children:[d(h)," AFN"]})]}),i.jsxs("div",{className:s?"":"bill-grand",children:[i.jsx("dt",{children:s?"Paid":"Paid now"}),i.jsxs("dd",{children:[d(p)," AFN"]})]}),!s&&i.jsxs("div",{children:[i.jsx("dt",{children:"Method"}),i.jsx("dd",{children:u(e.sale_payment?.payment_method)})]}),i.jsxs("div",{className:"bill-due",children:[i.jsx("dt",{children:"Remaining"}),i.jsxs("dd",{children:[d(a)," AFN"]})]})]}),i.jsx("p",{className:"bill-status",children:a<=0?"PAID":"PARTIAL"}),i.jsxs("footer",{className:"bill-foot",children:[i.jsx("p",{children:"Thank you"}),l.address&&i.jsx("p",{children:l.address}),i.jsx("p",{children:x})]})]})}),i.jsx("style",{children:`
                html, body { margin: 0; background: #f4f4f5; color: #111; }
                .bill-toolbar { display: flex; justify-content: space-between; align-items: center; padding: 10px 16px; background: #fff; border-bottom: 1px solid #e5e5e5; }
                .bill-toolbar button { display: inline-flex; gap: 6px; align-items: center; border: 1px solid #d4d4d4; background: #fff; border-radius: 6px; padding: 6px 10px; font-size: 12px; cursor: pointer; }
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
                .bill-meta div, .bill-totals div { display: flex; justify-content: space-between; gap: 12px; margin: 3px 0; }
                .bill-meta dt, .bill-totals dt { color: #111; }
                .bill-meta dd, .bill-totals dd { margin: 0; text-align: right; white-space: nowrap; }
                .bill-items { width: 100%; border-collapse: collapse; margin-top: 8px; table-layout: auto; }
                .bill-items th { border-bottom: 1px solid #111; text-align: right; font-size: 11px; padding: 4px 0; white-space: nowrap; }
                .bill-items th:first-child, .bill-items td:first-child { text-align: left; white-space: normal; word-break: break-word; }
                .bill-items td { padding: 4px 0; vertical-align: top; border-bottom: 1px dotted #ccc; white-space: nowrap; }
                .bill-grand { margin-top: 6px; padding-top: 6px; border-top: 1px solid #111; font-size: 14px; }
                .bill-due { font-size: 13px; }
                .bill-status { margin: 12px 0 0; letter-spacing: .16em; }
                .bill-foot { margin-top: 12px; padding-top: 8px; border-top: 1px solid #111; font-size: 11px; }
                @media print {
                    html, body { background: #fff; }
                    .no-print { display: none !important; }
                    .bill-page { padding: 0; display: block; }
                    .bill { width: fit-content; min-width: 72mm; max-width: 120mm; height: auto; box-shadow: none; }
                    @page { size: auto; margin: 4mm; }
                }
            `})]})}export{k as default};

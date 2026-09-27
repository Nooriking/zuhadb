import{r as h,j as i}from"./react-vendor-D89vF4rI.js";import{L as p}from"./inertia-CXinea7p.js";import{A as b,V as m}from"./icons-lvCR970c.js";import"./charts-DewAyPor.js";const l=e=>new Intl.NumberFormat("en-US",{maximumFractionDigits:0}).format(typeof e=="string"?parseFloat(e)||0:e||0),j=e=>({cash:"Cash",card:"Card",bank:"Bank",mobile:"Mobile",other:"Other"})[e]||e;function w({sale:e,company:t,printedAt:s}){h.useEffect(()=>{const n=setTimeout(()=>window.print(),500);return()=>clearTimeout(n)},[]);const r=e.customer_name_snapshot??e.customer?.name??"Walk-in",d=e.customer_phone_snapshot??e.customer?.phone??null,a=new Date(e.sale_date).toLocaleDateString("en-GB"),o=s.split(" ")[1]||"",c=e.payment_status==="paid"?"PAID":e.payment_status==="partial"?"PARTIAL":"UNPAID";return i.jsxs(i.Fragment,{children:[i.jsx(p,{title:`Invoice ${e.invoice_number}`}),i.jsxs("div",{className:"no-print bill-toolbar",children:[i.jsxs("a",{href:"/sales",children:[i.jsx(b,{size:14})," Back"]}),i.jsxs("button",{type:"button",onClick:()=>window.print(),children:[i.jsx(m,{size:14})," Print"]})]}),i.jsx("main",{className:"bill-page",children:i.jsxs("article",{className:"bill",children:[i.jsxs("header",{className:"bill-head",children:[t.logo&&i.jsx("img",{loading:"lazy",decoding:"async",className:"bill-logo",src:t.logo,alt:t.name}),i.jsx("p",{className:"bill-brand",children:t.name}),t.slogan&&i.jsx("p",{children:t.slogan}),t.phone&&i.jsxs("p",{children:["Tel: ",t.phone]}),t.address&&i.jsx("p",{children:t.address})]}),i.jsx("p",{className:"bill-kind",children:"Invoice"}),i.jsxs("dl",{className:"bill-meta",children:[i.jsxs("div",{children:[i.jsx("dt",{children:"Invoice"}),i.jsx("dd",{children:e.invoice_number})]}),i.jsxs("div",{children:[i.jsx("dt",{children:"Date"}),i.jsxs("dd",{children:[a," ",o]})]}),i.jsxs("div",{children:[i.jsx("dt",{children:"Cashier"}),i.jsx("dd",{children:e.creator?.name||"—"})]}),i.jsxs("div",{children:[i.jsx("dt",{children:"Customer"}),i.jsx("dd",{children:r})]}),d&&i.jsxs("div",{children:[i.jsx("dt",{children:"Phone"}),i.jsx("dd",{children:d})]})]}),i.jsxs("table",{className:"bill-items",children:[i.jsx("thead",{children:i.jsxs("tr",{children:[i.jsx("th",{children:"Item"}),i.jsx("th",{children:"Qty"}),i.jsx("th",{children:"Price"}),i.jsx("th",{children:"Total"})]})}),i.jsx("tbody",{children:e.items.map((n,x)=>i.jsxs("tr",{children:[i.jsxs("td",{children:[x+1,". ",n.product_name_snapshot||"Product"]}),i.jsx("td",{children:n.quantity}),i.jsx("td",{children:l(n.sale_price_afn)}),i.jsx("td",{children:l(n.line_total_afn)})]},n.id))})]}),i.jsxs("dl",{className:"bill-totals",children:[i.jsxs("div",{children:[i.jsx("dt",{children:"Subtotal"}),i.jsxs("dd",{children:[l(e.subtotal_afn)," AFN"]})]}),parseFloat(e.discount_afn)>0&&i.jsxs("div",{children:[i.jsx("dt",{children:"Discount"}),i.jsxs("dd",{children:["-",l(e.discount_afn)," AFN"]})]}),parseFloat(e.tax_afn)>0&&i.jsxs("div",{children:[i.jsx("dt",{children:"Tax"}),i.jsxs("dd",{children:["+",l(e.tax_afn)," AFN"]})]}),i.jsxs("div",{children:[i.jsx("dt",{children:"Previous due"}),i.jsxs("dd",{children:[l(e.previous_due_afn||0)," AFN"]})]}),i.jsxs("div",{children:[i.jsx("dt",{children:"Today"}),i.jsxs("dd",{children:[l(e.new_sale_amount_afn||e.total_afn)," AFN"]})]}),i.jsxs("div",{className:"bill-grand",children:[i.jsx("dt",{children:"Total"}),i.jsxs("dd",{children:[l(e.grand_total_due_afn||e.total_afn)," AFN"]})]}),i.jsxs("div",{children:[i.jsx("dt",{children:"Paid"}),i.jsxs("dd",{children:[l(e.received_now_afn||e.paid_afn)," AFN"]})]}),i.jsxs("div",{className:"bill-due",children:[i.jsx("dt",{children:"Remaining"}),i.jsxs("dd",{children:[l(e.remaining_due_afn||e.due_afn)," AFN"]})]})]}),e.payments.length>0&&i.jsx("ul",{className:"bill-pays",children:e.payments.map(n=>i.jsxs("li",{children:[i.jsx("span",{children:j(n.payment_method)}),i.jsxs("span",{children:[l(n.amount_afn)," AFN"]})]},n.id))}),i.jsx("p",{className:"bill-status",children:c}),e.notes&&i.jsx("p",{className:"bill-note",children:e.notes}),i.jsxs("footer",{className:"bill-foot",children:[i.jsx("p",{children:"Thank you"}),i.jsxs("p",{children:["Exchange within ",t.exchange_window_days??3," days with this invoice"]}),t.address&&i.jsx("p",{children:t.address}),(t.support_phone||t.phone)&&i.jsxs("p",{children:["Support ",t.support_phone||t.phone]}),t.manufacturer&&i.jsx("p",{children:t.manufacturer}),i.jsxs("p",{children:[s," · Print ",e.printed_count+1]})]})]})}),i.jsx("style",{children:`
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
            `})]})}export{w as default};

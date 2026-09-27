import{r,j as i}from"./react-vendor-D89vF4rI.js";import{L as o}from"./inertia-CXinea7p.js";import{A as x,V as h}from"./icons-lvCR970c.js";import"./charts-DewAyPor.js";const d=e=>new Intl.NumberFormat("en-US",{maximumFractionDigits:0}).format(e||0);function g({company:e,supplier:s,printedAt:n,receipt:l}){return r.useEffect(()=>{const t=setTimeout(()=>window.print(),500);return()=>clearTimeout(t)},[]),i.jsxs(i.Fragment,{children:[i.jsx(o,{title:`${l.title} ${l.number}`}),i.jsxs("div",{className:"no-print bill-toolbar",children:[i.jsxs("a",{href:"/suppliers",children:[i.jsx(x,{size:14})," Suppliers"]}),i.jsx("a",{href:`/suppliers/${s.id}`,children:"Supplier"}),i.jsxs("button",{type:"button",onClick:()=>window.print(),children:[i.jsx(h,{size:14})," Print"]})]}),i.jsx("main",{className:"bill-page",children:i.jsxs("article",{className:"bill",children:[i.jsxs("header",{className:"bill-head",children:[e.logo&&i.jsx("img",{loading:"lazy",decoding:"async",className:"bill-logo",src:e.logo,alt:e.name}),i.jsx("p",{className:"bill-brand",children:e.name}),e.phone&&i.jsxs("p",{children:["Tel: ",e.phone]}),e.address&&i.jsx("p",{children:e.address})]}),i.jsx("p",{className:"bill-kind",children:l.title}),i.jsxs("dl",{className:"bill-meta",children:[i.jsxs("div",{children:[i.jsx("dt",{children:"Number"}),i.jsx("dd",{children:l.number})]}),i.jsxs("div",{children:[i.jsx("dt",{children:"Date"}),i.jsx("dd",{children:l.date||"—"})]}),i.jsxs("div",{children:[i.jsx("dt",{children:"Supplier"}),i.jsx("dd",{children:s.name})]}),s.phone&&i.jsxs("div",{children:[i.jsx("dt",{children:"Phone"}),i.jsx("dd",{children:s.phone})]}),l.method&&i.jsxs("div",{children:[i.jsx("dt",{children:"Method"}),i.jsx("dd",{children:l.method})]})]}),i.jsxs("table",{className:"bill-items",children:[i.jsx("thead",{children:i.jsxs("tr",{children:[i.jsx("th",{children:"Description"}),i.jsx("th",{children:"Amount"})]})}),i.jsx("tbody",{children:l.lines.map((t,a)=>i.jsxs("tr",{children:[i.jsxs("td",{children:[t.name,t.quantity?` × ${t.quantity}`:""]}),i.jsx("td",{children:d(t.amount)})]},`${t.name}-${a}`))})]}),i.jsxs("dl",{className:"bill-totals",children:[i.jsxs("div",{children:[i.jsx("dt",{children:"Amount"}),i.jsxs("dd",{children:[d(l.amount)," AFN"]})]}),i.jsxs("div",{children:[i.jsx("dt",{children:"Paid"}),i.jsxs("dd",{children:[d(l.paid)," AFN"]})]}),i.jsxs("div",{className:"bill-due",children:[i.jsx("dt",{children:"Due on this bill"}),i.jsxs("dd",{children:[d(l.remaining)," AFN"]})]}),i.jsxs("div",{className:"bill-grand",children:[i.jsx("dt",{children:"Supplier balance"}),i.jsxs("dd",{children:[d(l.balance)," AFN"]})]})]}),i.jsx("p",{className:"bill-status",children:l.status}),l.notes&&i.jsx("p",{className:"bill-note",children:l.notes}),i.jsxs("footer",{className:"bill-foot",children:[i.jsx("p",{children:n}),e.address&&i.jsx("p",{children:e.address})]})]})}),i.jsx("style",{children:`
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
            `})]})}export{g as default};

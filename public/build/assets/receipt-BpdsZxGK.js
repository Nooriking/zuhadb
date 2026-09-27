import{r as a,j as i}from"./react-vendor-D89vF4rI.js";import{L as o}from"./inertia-CXinea7p.js";import{A as r,V as x}from"./icons-lvCR970c.js";import"./charts-DewAyPor.js";const d=l=>new Intl.NumberFormat("en-US",{minimumFractionDigits:2,maximumFractionDigits:2}).format(l||0);function m({company:l,investor:s,printedAt:n,receipt:t}){return a.useEffect(()=>{const e=setTimeout(()=>window.print(),500);return()=>clearTimeout(e)},[]),i.jsxs(i.Fragment,{children:[i.jsx(o,{title:`${t.title} ${t.number}`}),i.jsxs("div",{className:"no-print bill-toolbar",children:[i.jsxs("a",{href:`/investors/${s.id}`,children:[i.jsx(r,{size:14})," Investor"]}),i.jsxs("button",{type:"button",onClick:()=>window.print(),children:[i.jsx(x,{size:14})," Print"]})]}),i.jsx("main",{className:"bill-page",children:i.jsxs("article",{className:"bill",children:[i.jsxs("header",{className:"bill-head",children:[l.logo&&i.jsx("img",{loading:"lazy",decoding:"async",className:"bill-logo",src:l.logo,alt:l.name}),i.jsx("p",{className:"bill-brand",children:l.name}),l.phone&&i.jsxs("p",{children:["Tel: ",l.phone]}),l.address&&i.jsx("p",{children:l.address})]}),i.jsx("p",{className:"bill-kind",children:t.title}),i.jsxs("dl",{className:"bill-meta",children:[i.jsxs("div",{children:[i.jsx("dt",{children:"Number"}),i.jsx("dd",{children:t.number})]}),i.jsxs("div",{children:[i.jsx("dt",{children:"Date"}),i.jsx("dd",{children:t.date||"—"})]}),i.jsxs("div",{children:[i.jsx("dt",{children:"Investor"}),i.jsx("dd",{children:s.name})]}),s.phone&&i.jsxs("div",{children:[i.jsx("dt",{children:"Phone"}),i.jsx("dd",{children:s.phone})]})]}),i.jsxs("table",{className:"bill-items",children:[i.jsx("thead",{children:i.jsxs("tr",{children:[i.jsx("th",{children:"Description"}),i.jsx("th",{children:"USD"})]})}),i.jsx("tbody",{children:t.lines.map(e=>i.jsxs("tr",{children:[i.jsx("td",{children:e.name}),i.jsx("td",{children:d(e.amount)})]},e.name))})]}),i.jsxs("dl",{className:"bill-totals",children:[i.jsxs("div",{className:"bill-grand",children:[i.jsx("dt",{children:"Paid"}),i.jsxs("dd",{children:[d(t.amount)," USD"]})]}),i.jsxs("div",{children:[i.jsx("dt",{children:"Account balance"}),i.jsxs("dd",{children:[d(t.balance)," USD"]})]})]}),i.jsx("p",{className:"bill-status",children:"Paid in full. Account is zero."}),t.notes&&i.jsx("p",{className:"bill-note",children:t.notes}),i.jsxs("footer",{className:"bill-foot",children:[i.jsx("p",{children:n}),l.address&&i.jsx("p",{children:l.address})]})]})}),i.jsx("style",{children:`
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
                .bill-head, .bill-foot, .bill-kind, .bill-status, .bill-note { text-align: center; }
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
                .bill-status { margin: 12px 0 0; }
                .bill-note { margin: 8px 0 0; }
                .bill-foot { margin-top: 12px; padding-top: 8px; border-top: 1px solid #111; font-size: 11px; }
                @media print {
                    html, body { background: #fff; }
                    .no-print { display: none !important; }
                    .bill-page { padding: 0; display: block; }
                    .bill { width: fit-content; min-width: 72mm; max-width: 120mm; height: auto; box-shadow: none; }
                    @page { size: auto; margin: 4mm; }
                }
            `})]})}export{m as default};

    <style>
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111; background: white; font: 11pt/1.4 Arial, sans-serif; }
        main { max-width: 182mm; margin: 24px auto; padding: 0 12px; }
        h1 { font-size: 20pt; margin: 0 0 6px; }
        h2 { font-size: 13pt; margin: 22px 0 6px; }
        p { margin: 4px 0; }
        address { font-style: normal; white-space: pre-line; overflow-wrap: anywhere; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; table-layout: fixed; }
        th, td { padding: 9px 6px; text-align: left; border-bottom: 1px solid #aaa; vertical-align: top; overflow-wrap: anywhere; }
        th { font-size: 10pt; }
        .product { width: 32%; } .sku { width: 27%; } .quantity { width: 10%; text-align: right; }
        .print-brand { display:flex; align-items:center; gap:14px; margin-bottom:12px; break-inside:avoid; }
        .print-brand img, .print-brand svg { width:64px; height:64px; flex-shrink:0; object-fit:contain; }
        .print-brand h1 { margin:0; }
        th.numeric, td.numeric { text-align:right; font-variant-numeric:tabular-nums; }
        .totals strong { font-variant-numeric:tabular-nums; text-align:right; }
        .print-controls { margin-bottom: 24px; }
        button { padding: 10px 18px; cursor: pointer; }
        button:focus-visible { outline: 3px solid #111; outline-offset: 3px; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; }
        h1, h2 { break-after: avoid; }
        .totals, .cod-amount, .sender-address { break-inside: avoid; }
        @media print {
            html, body { margin: 0; padding: 0; }
            main { margin: 0; padding: 12mm; max-width: none; width: 100%; box-decoration-break: clone; -webkit-box-decoration-break: clone; }
            .print-controls { display: none !important; }
            a { color: inherit; text-decoration: none; }
        }
    </style>
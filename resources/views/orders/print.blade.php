<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>فاتورة الطلب - مستند غير محاسبي — {{ $order->order_number }}</title>
    <style>
        @page {
            size: A4;
            margin: 12mm;
        }

        :root {
            color-scheme: light;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 24px;
            background: #f3f4f6;
            color: #111827;
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            font-size: 14px;
            line-height: 1.7;
        }

        .sheet {
            max-width: 210mm;
            margin: 0 auto;
            padding: 28px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
        }

        .actions {
            display: flex;
            justify-content: flex-start;
            margin-bottom: 20px;
        }

        .actions button {
            padding: 10px 22px;
            border: 0;
            border-radius: 8px;
            background: #c91424;
            color: #ffffff;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }

        .doc-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding-bottom: 16px;
            border-bottom: 2px solid #111827;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand img {
            width: 44px;
            height: 44px;
            object-fit: contain;
        }

        .brand-name {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
        }

        .doc-reference {
            text-align: left;
            font-size: 13px;
            color: #374151;
        }

        .doc-reference strong {
            color: #111827;
        }

        .doc-title {
            margin: 22px 0 4px;
            font-size: 22px;
            text-align: center;
        }

        .doc-subtitle {
            margin: 0 0 22px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
        }

        .section {
            margin-bottom: 22px;
        }

        .section-title {
            margin: 0 0 10px;
            padding-bottom: 6px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 16px;
        }

        .meta-grid,
        .customer-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 6px 24px;
            margin: 0;
        }

        .field {
            display: flex;
            gap: 8px;
        }

        .field dt {
            min-width: 110px;
            color: #6b7280;
        }

        .field dd {
            margin: 0;
            font-weight: 600;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .items th,
        .items td {
            padding: 8px 10px;
            border: 1px solid #d1d5db;
            text-align: right;
            vertical-align: top;
        }

        .items th {
            background: #f9fafb;
            font-weight: 700;
        }

        .items tbody tr {
            page-break-inside: avoid;
        }

        .num {
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .code {
            direction: ltr;
            text-align: right;
            unicode-bidi: embed;
        }

        .totals {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 6px;
            margin-top: 16px;
        }

        .totals-row {
            display: flex;
            gap: 12px;
            min-width: 280px;
            padding: 6px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: #f9fafb;
        }

        .totals-row span {
            min-width: 170px;
            color: #374151;
        }

        .note {
            margin: 10px 0 0;
            padding: 8px 12px;
            border-radius: 6px;
            background: #fff7ed;
            color: #9a3412;
            font-size: 12px;
        }

        .footer-note {
            margin-top: 28px;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 11px;
            color: #9ca3af;
        }

        @media print {
            body {
                padding: 0;
                background: #ffffff;
            }

            .sheet {
                max-width: none;
                padding: 0;
                border: 0;
                border-radius: 0;
            }

            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="actions no-print">
            <button type="button" onclick="window.print()">طباعة</button>
        </div>

        <header class="doc-header">
            <div class="brand">
                @if (is_string($brandLogo) && filled($brandLogo))
                    <img src="{{ $brandLogo }}" alt="{{ $brandName }}">
                @endif

                <h1 class="brand-name">{{ $brandName }}</h1>
            </div>

            <div class="doc-reference">
                <div>رقم الطلب: <strong>{{ $order->order_number }}</strong></div>
                <div>تاريخ الطلب: <strong>{{ $order->created_at?->format('Y-m-d H:i') }}</strong></div>
            </div>
        </header>

        <h2 class="doc-title">فاتورة الطلب - مستند غير محاسبي</h2>
        <p class="doc-subtitle">مستند تشغيلي للاستخدام الداخلي وخدمة العملاء، ولا يُستخدم لأغراض محاسبية.</p>

        <section class="section">
            <h3 class="section-title">بيانات العميل</h3>
            <dl class="customer-grid">
                @if (filled($order->customer?->name))
                    <div class="field"><dt>اسم العميل</dt><dd>{{ $order->customer->name }}</dd></div>
                @endif
                @if (filled($order->customer?->company_name))
                    <div class="field"><dt>الشركة / المحل</dt><dd>{{ $order->customer->company_name }}</dd></div>
                @endif
                @if (filled($order->customer?->phone))
                    <div class="field"><dt>رقم الموبايل</dt><dd class="num">{{ $order->customer->phone }}</dd></div>
                @endif
                @if (filled($order->customer?->whatsapp))
                    <div class="field"><dt>واتساب</dt><dd class="num">{{ $order->customer->whatsapp }}</dd></div>
                @endif
                @if (filled($order->customer?->governorate))
                    <div class="field"><dt>المحافظة</dt><dd>{{ $order->customer->governorate }}</dd></div>
                @endif
                @if (filled($order->customer?->city))
                    <div class="field"><dt>المدينة</dt><dd>{{ $order->customer->city }}</dd></div>
                @endif
                @if (filled($order->customer?->address))
                    <div class="field"><dt>العنوان</dt><dd>{{ $order->customer->address }}</dd></div>
                @endif
            </dl>
        </section>

        <section class="section">
            <h3 class="section-title">بنود الطلب</h3>
            <table class="items">
                <thead>
                    <tr>
                        <th>كود المنتج</th>
                        <th>اسم المنتج</th>
                        <th>اللون</th>
                        <th>المقاس</th>
                        <th>الكمية لكل لون</th>
                        <th>عدد الألوان</th>
                        <th>إجمالي القطع</th>
                        <th>سعر الوحدة</th>
                        <th>إجمالي البند</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td class="code">{{ $item->product_code }}</td>
                            <td>{{ $item->product_name }}</td>
                            <td>{{ $item->color ?? '—' }}</td>
                            <td>{{ $item->size ?? '—' }}</td>
                            <td class="num">{{ $item->requested_quantity }}</td>
                            <td class="num">{{ $item->color_count }}</td>
                            <td class="num">{{ $item->quantity }}</td>
                            <td class="num">
                                @if ($item->unit_price === null)
                                    السعر عند الطلب
                                @else
                                    {{ number_format((float) $item->unit_price, 2, '.', ',') }} ج.م
                                @endif
                            </td>
                            <td class="num">
                                @if ($item->unit_price === null)
                                    —
                                @else
                                    {{ number_format((float) bcmul((string) $item->unit_price, (string) $item->quantity, 2), 2, '.', ',') }} ج.م
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="totals">
                <div class="totals-row">
                    <span>إجمالي الكميات المطلوبة</span>
                    <strong class="num">{{ $order->total_quantity }}</strong>
                </div>

                @if ($hasPublicPricedItems)
                    <div class="totals-row">
                        <span>{{ $hasRequestPriceItems ? 'إجمالي العناصر ذات السعر المعلن' : 'إجمالي الطلب' }}</span>
                        <strong class="num">{{ number_format((float) $publicItemsTotal, 2, '.', ',') }} ج.م</strong>
                    </div>
                @endif
            </div>

            @if ($hasRequestPriceItems)
                <p class="note">لا يشمل المنتجات التي يتم تحديد سعرها بشكل منفصل.</p>
            @endif
        </section>

        <footer class="footer-note">
            هذا المستند غير محاسبي — رقم الطلب {{ $order->order_number }} هو المرجع الوحيد.
        </footer>
    </div>
</body>
</html>

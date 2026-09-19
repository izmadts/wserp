@php
    use App\Support\UrduText;

    // "1,234.5" - no trailing zeros, like the sale agent app's invoice
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ','), '0'), '.');
    $fonts = str_replace('\\', '/', resource_path('fonts'));

    $statusText = match ($sale->status) {
        'paid' => 'PAID',
        'partial' => 'PARTIALLY PAID',
        'confirmed' => 'CONFIRMED',
        'cancelled' => 'CANCELLED',
        default => 'PENDING CONFIRMATION',
    };
    $statusColor = match ($sale->status) {
        'paid' => '#2E7D32',
        'partial' => '#EF6C00',
        'confirmed' => '#1565C0',
        'cancelled' => '#C62828',
        default => '#616161',
    };
    $methodText = fn ($m) => ['cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'cheque' => 'Cheque', 'credit_card' => 'Credit card'][$m] ?? ucfirst(str_replace('_', ' ', (string) $m));

    $approved = $sale->payments->where('status', 'approved');
    $pending = $sale->payments->where('status', 'pending');
    $pendingTotal = (float) $pending->sum('amount');
    $discountAmount = $sale->discount_type === 'percentage' ? (float) $sale->sub_total * (float) $sale->discount / 100 : (float) $sale->discount;
    $due = (float) $sale->due_amount;

    // 1 Mun = 40 kg: the rate for 40 kg beside the per-kg rate (weight items only)
    $munRate = function ($item) {
        $unit = strtolower(trim((string) ($item->product->unit ?? '')));
        if (in_array($unit, ['kg', 'kgs', 'kilogram', 'kilograms'], true)) return (float) $item->unit_price * 40;
        if (in_array($unit, ['g', 'gm', 'gram', 'grams'], true)) return (float) $item->unit_price * 40 * 1000;
        return null;
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Invoice {{ $sale->invoice_no }}</title>
<style>
    @font-face { font-family: 'NotoSans'; font-weight: normal; font-style: normal; src: url('{{ $fonts }}/NotoSans-Regular.ttf') format('truetype'); }
    @font-face { font-family: 'NotoSans'; font-weight: bold; font-style: normal; src: url('{{ $fonts }}/NotoSans-Bold.ttf') format('truetype'); }
    @font-face { font-family: 'NotoNaskh'; font-weight: normal; font-style: normal; src: url('{{ $fonts }}/NotoNaskhArabic-Regular.ttf') format('truetype'); }
    @font-face { font-family: 'NotoNaskh'; font-weight: bold; font-style: normal; src: url('{{ $fonts }}/NotoNaskhArabic-Bold.ttf') format('truetype'); }

    @page { margin: 30px 34px 34px 34px; }
    body { font-family: 'NotoSans', 'DejaVu Sans', sans-serif; font-size: 10pt; color: #212121; }
    .ur { font-family: 'NotoNaskh', 'NotoSans'; font-size: 1.15em; }
    table { border-collapse: collapse; width: 100%; }
    td, th { vertical-align: top; }
    .brand { color: #1565C0; }
    .grey { color: #616161; }
    .label { font-size: 8.5pt; font-weight: bold; color: #1565C0; }
    .th { background: #1565C0; color: #ffffff; font-size: 9.5pt; font-weight: bold; padding: 6px 5px; }
    .thl { background: #E3F2FD; font-size: 9pt; font-weight: bold; padding: 4px 5px; }
    .td { padding: 6px 5px; border-bottom: 0.4px solid #BDBDBD; font-size: 10pt; }
    .right { text-align: right; }
    .center { text-align: center; }
    .pill { display: inline-block; padding: 3px 9px; border-radius: 10px; color: #ffffff; font-size: 9pt; font-weight: bold; }
    .box { padding: 9px 10px; }
</style>
</head>
<body>

{{-- ============ Header ============ --}}
<table>
    <tr>
        <td style="width: 58%;">
            <div class="brand" style="font-size: 20pt; font-weight: bold;">{!! UrduText::html($company['name']) !!}</div>
            @if(!empty($company['address']))<div class="grey" style="font-size: 9pt; margin-top: 3px;">{!! UrduText::html($company['address']) !!}</div>@endif
            @if(!empty($company['phone']))<div class="grey" style="font-size: 9pt; margin-top: 2px;">Tel: {{ $company['phone'] }}</div>@endif
        </td>
        <td class="right" style="width: 42%;">
            <div class="brand" style="font-size: 24pt; font-weight: bold; line-height: 1.1;">INVOICE</div>
            <div style="font-size: 12pt; font-weight: bold; margin-top: 4px;">{{ $sale->invoice_no }}</div>
            <div style="font-size: 10pt;">Date: {{ $sale->sale_date->format('d M Y') }}</div>
            <div style="margin-top: 5px;"><span class="pill" style="background: {{ $statusColor }};">{{ $statusText }}</span></div>
        </td>
    </tr>
</table>
<div style="border-top: 1.5px solid #1565C0; margin: 10px 0 9px 0;"></div>

{{-- ============ Bill to / Details ============ --}}
<table>
    <tr>
        <td style="width: 49%; background: #E3F2FD; border-radius: 6px;" class="box">
            <div class="label">BILL TO</div>
            <div style="font-size: 12pt; font-weight: bold; margin-top: 4px;">{!! UrduText::html($sale->customer->name ?? '-') !!}</div>
            @if(!empty($sale->customer->mobile) || !empty($sale->customer->phone))
                <div style="font-size: 9.5pt; margin-top: 2px;">Phone: {{ $sale->customer->mobile ?: $sale->customer->phone }}</div>
            @endif
            @if(!empty($sale->customer->address) || !empty($sale->customer->city))
                <div style="font-size: 9.5pt; margin-top: 2px;">{!! UrduText::html(trim(($sale->customer->address ?? '') . (!empty($sale->customer->address) && !empty($sale->customer->city) ? ', ' : '') . ($sale->customer->city ?? ''))) !!}</div>
            @endif
        </td>
        <td style="width: 2%;"></td>
        <td style="width: 49%; border: 0.6px solid #BDBDBD; border-radius: 6px;" class="box">
            <div class="label">DETAILS</div>
            <table style="margin-top: 4px;">
                <tr><td class="grey" style="width: 92px; font-size: 9pt; padding-bottom: 3px; white-space: nowrap;">Terms</td><td style="font-weight: bold; padding-bottom: 3px;">{{ $sale->payment_term === 'credit' ? 'Credit' : 'Cash' }}</td></tr>
                @if($sale->agent)
                <tr><td class="grey" style="font-size: 9pt; padding-bottom: 3px; white-space: nowrap;">Sales agent</td><td style="padding-bottom: 3px;">{!! UrduText::html($sale->agent->name) !!}</td></tr>
                @if(!empty($sale->agent->phone))<tr><td class="grey" style="font-size: 9pt; white-space: nowrap;">Agent phone</td><td>{{ $sale->agent->phone }}</td></tr>@endif
                @endif
            </table>
        </td>
    </tr>
</table>

{{-- ============ Items ============ --}}
<table style="margin-top: 14px; border-bottom: 0.8px solid #BDBDBD;">
    <thead>
        <tr>
            <th class="th center" style="width: 22px;">#</th>
            <th class="th" style="text-align: left;">Item</th>
            <th class="th right" style="width: 58px;">Qty</th>
            <th class="th right" style="width: 54px;">Rate</th>
            <th class="th right" style="width: 78px;">Rate / Mun<br>(40 kg)</th>
            <th class="th right" style="width: 70px;">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach($sale->items as $i => $item)
        @php $mun = $munRate($item); $unit = trim((string) ($item->product->unit ?? '')); @endphp
        <tr>
            <td class="td center">{{ $i + 1 }}</td>
            <td class="td">{!! UrduText::html($item->product->name ?? 'Item') !!}</td>
            <td class="td right">{{ $num($item->quantity) }}{{ $unit !== '' ? ' ' . $unit : '' }}</td>
            <td class="td right">{{ $num($item->unit_price) }}</td>
            <td class="td right" @if($mun === null) style="color: #616161;" @endif>{{ $mun === null ? '-' : $num($mun) }}</td>
            <td class="td right" style="font-weight: bold;">{{ $num($item->total_price) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- ============ Notes + totals ============ --}}
<table style="margin-top: 12px;">
    <tr>
        <td style="padding-right: 24px;">
            @if(trim((string) $sale->notes) !== '')
                <div class="label">NOTES</div>
                <div class="grey" style="font-size: 9.5pt; margin-top: 3px;">{!! UrduText::html($sale->notes) !!}</div>
            @endif
        </td>
        <td style="width: 235px;">
            <table>
                <tr><td style="padding: 2.5px 0;">Sub total</td><td class="right" style="padding: 2.5px 0;">Rs. {{ $num($sale->sub_total) }}</td></tr>
                @if($discountAmount > 0)<tr><td style="padding: 2.5px 0;">Discount</td><td class="right" style="padding: 2.5px 0;">- Rs. {{ $num($discountAmount) }}</td></tr>@endif
                @if((float) $sale->tax > 0)<tr><td style="padding: 2.5px 0;">Tax</td><td class="right" style="padding: 2.5px 0;">Rs. {{ $num($sale->tax) }}</td></tr>@endif
                @if((float) $sale->shipping_cost > 0)<tr><td style="padding: 2.5px 0;">Shipping</td><td class="right" style="padding: 2.5px 0;">Rs. {{ $num($sale->shipping_cost) }}</td></tr>@endif
                <tr><td colspan="2" style="border-top: 0.6px solid #BDBDBD; padding-top: 3px;"></td></tr>
                <tr class="brand"><td style="font-size: 12.5pt; font-weight: bold; padding: 2.5px 0;">TOTAL</td><td class="right" style="font-size: 12.5pt; font-weight: bold; padding: 2.5px 0;">Rs. {{ $num($sale->total_amount) }}</td></tr>
                @if((float) $sale->paid_amount > 0)<tr style="color: #2E7D32;"><td style="padding: 2.5px 0;">Paid</td><td class="right" style="padding: 2.5px 0;">Rs. {{ $num($sale->paid_amount) }}</td></tr>@endif
                @if($pendingTotal > 0)<tr style="color: #EF6C00;"><td style="font-size: 9.5pt; padding: 2.5px 0;">Awaiting confirmation</td><td class="right" style="font-size: 9.5pt; padding: 2.5px 0;">Rs. {{ $num($pendingTotal) }}</td></tr>@endif
                <tr><td colspan="2" style="padding-top: 4px;">
                    <table style="background: {{ $due > 0 ? '#FFEBEE' : '#E8F5E9' }}; border-radius: 4px; color: {{ $due > 0 ? '#C62828' : '#2E7D32' }};">
                        <tr>
                            <td style="font-size: 12pt; font-weight: bold; padding: 5px 8px; white-space: nowrap;">{{ $due > 0 ? 'BALANCE DUE' : 'FULLY PAID' }}</td>
                            <td class="right" style="font-size: 12pt; font-weight: bold; padding: 5px 8px; white-space: nowrap;">Rs. {{ $num($due > 0 ? $due : 0) }}</td>
                        </tr>
                    </table>
                </td></tr>
            </table>
        </td>
    </tr>
</table>

{{-- ============ Payments ============ --}}
@if($approved->isNotEmpty() || $pending->isNotEmpty())
<div class="label" style="margin-top: 16px;">PAYMENTS</div>
<table style="margin-top: 4px; border-bottom: 0.6px solid #BDBDBD;">
    <thead>
        <tr>
            <th class="thl" style="text-align: left; width: 25%;">Date</th>
            <th class="thl" style="text-align: left; width: 25%;">Method</th>
            <th class="thl" style="text-align: left; width: 25%;">Status</th>
            <th class="thl right" style="width: 25%;">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach($approved->concat($pending) as $p)
        <tr>
            <td class="td" style="font-size: 9.5pt;">{{ optional($p->payment_date)->format('d M Y') }}</td>
            <td class="td" style="font-size: 9.5pt;">{{ $methodText($p->payment_method) }}@if($p->reference_no) <span class="grey" style="font-size: 8.5pt;">({!! UrduText::html($p->reference_no) !!})</span>@endif</td>
            <td class="td" style="font-size: 9pt; color: {{ $p->status === 'pending' ? '#EF6C00' : '#2E7D32' }};">{{ $p->status === 'pending' ? 'Awaiting confirmation' : 'Received' }}</td>
            <td class="td right" style="font-size: 9.5pt; font-weight: bold;">Rs. {{ $num($p->amount) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<div class="center brand" style="margin-top: 26px; font-size: 11pt; font-weight: bold;">Thank you for your business!</div>
<div class="center grey" style="margin-top: 3px; font-size: 8pt;">This is a computer generated invoice.</div>

</body>
</html>

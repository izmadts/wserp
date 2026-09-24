@extends('layouts.admin')

@section('title', 'Business Summary')
@section('page-title', 'Business Summary')

@php
    $sig = [
        'green'  => ['bg' => 'bg-green-50 border-green-300',   'dot' => 'bg-green-500',  'text' => 'text-green-800',  'label' => 'GROWING',    'icon' => 'fa-arrow-trend-up'],
        'yellow' => ['bg' => 'bg-yellow-50 border-yellow-300', 'dot' => 'bg-yellow-400', 'text' => 'text-yellow-800', 'label' => 'STEADY / MIXED', 'icon' => 'fa-minus'],
        'red'    => ['bg' => 'bg-red-50 border-red-300',       'dot' => 'bg-red-500',    'text' => 'text-red-800',    'label' => 'NOT GROWING', 'icon' => 'fa-arrow-trend-down'],
    ][$signal];
    $rs = fn ($v) => 'Rs. ' . number_format($v, 0);
    $compareLabel = ['previous_period' => 'previous period', 'previous_year' => 'same period last year', 'custom' => 'selected comparison period'][$compareMode] ?? 'previous period';
    $chg = function ($v, $goodWhenUp = true) use ($compareLabel) {
        if ($v === null) return '<span class="text-gray-400 text-xs">no previous data</span>';
        $up = $v >= 0;
        $good = $goodWhenUp ? $up : !$up;
        return '<span class="text-xs font-medium ' . ($good ? 'text-green-600' : 'text-red-600') . '"><i class="fas ' . ($up ? 'fa-arrow-up' : 'fa-arrow-down') . ' mr-1"></i>' . number_format(abs($v), 1) . '% vs ' . e($compareLabel) . '</span>';
    };
@endphp

@section('content')
<div class="space-y-6">

    {{-- Period picker --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        @include('admin.reports.partials.period-picker', [
            'routeName' => 'admin.reports.business-summary',
            'from' => $curFrom, 'to' => $curTo,
            'compareMode' => $compareMode, 'compareFrom' => $prevFrom, 'compareTo' => $prevTo,
        ])
        <p class="text-sm text-gray-500">
            This {{ $unit }}: <strong>{{ date('d M', strtotime($curFrom)) }} - {{ date('d M Y', strtotime($curTo)) }}</strong>
            @if($prevFrom && $prevTo)
                &nbsp;vs&nbsp; {{ $compareLabel }}: {{ date('d M', strtotime($prevFrom)) }} - {{ date('d M Y', strtotime($prevTo)) }}
            @endif
        </p>
    </div>

    {{-- Signal --}}
    <div class="rounded-xl border-2 p-5 sm:p-6 {{ $sig['bg'] }}">
        <div class="flex items-center gap-5">
            <div class="flex flex-col items-center gap-1.5 bg-gray-800 rounded-2xl px-3 py-3 shrink-0" aria-hidden="true">
                <span class="w-6 h-6 rounded-full {{ $signal === 'red' ? 'bg-red-500' : 'bg-gray-600' }}"></span>
                <span class="w-6 h-6 rounded-full {{ $signal === 'yellow' ? 'bg-yellow-400' : 'bg-gray-600' }}"></span>
                <span class="w-6 h-6 rounded-full {{ $signal === 'green' ? 'bg-green-500' : 'bg-gray-600' }}"></span>
            </div>
            <div>
                <p class="text-xs font-semibold tracking-wider {{ $sig['text'] }}">{{ $sig['label'] }}</p>
                <h2 class="text-xl sm:text-2xl font-bold {{ $sig['text'] }}">{{ $headline }}</h2>
                <p class="text-sm {{ $sig['text'] }} mt-1">
                    Net {{ $c['net'] >= 0 ? 'profit' : 'loss' }} this {{ $unit }}: <strong>{{ $rs(abs($c['net'])) }}</strong>
                    ({{ $c['net_margin'] }}% of sales) &middot; {{ $compareLabel }}: {{ $p['net'] >= 0 ? 'profit' : 'loss' }} {{ $rs(abs($p['net'])) }}
                </p>
            </div>
        </div>
        <p class="text-xs text-gray-500 mt-4">
            Green = sales and profit both up. Red = loss, or sales and profit both down. Yellow = break-even (profit under 1% of sales) or mixed results.
        </p>
    </div>

    {{-- Simple money flow tiles --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4">
        <div class="bg-white rounded-xl shadow-card p-4">
            <p class="text-xs text-gray-500">Sales + other income</p>
            <p class="text-xl font-bold text-green-600">{{ $rs($c['revenue']) }}</p>
            {!! $chg($g['revenue']) !!}
        </div>
        <div class="bg-white rounded-xl shadow-card p-4">
            <p class="text-xs text-gray-500">Cost of goods sold</p>
            <p class="text-xl font-bold text-orange-600">{{ $rs($c['cogs']) }}</p>
            {!! $chg($g['cogs'], false) !!}
        </div>
        <div class="bg-white rounded-xl shadow-card p-4">
            <p class="text-xs text-gray-500">Gross profit <span class="text-gray-400">({{ $c['gross_margin'] }}%)</span></p>
            <p class="text-xl font-bold {{ $c['gross'] >= 0 ? 'text-blue-600' : 'text-red-600' }}">{{ $rs($c['gross']) }}</p>
            {!! $chg($g['gross']) !!}
        </div>
        <div class="bg-white rounded-xl shadow-card p-4">
            <p class="text-xs text-gray-500">Running expenses <span class="text-gray-400">({{ $expPct }}% of sales)</span></p>
            <p class="text-xl font-bold text-red-600">{{ $rs($c['expenses']) }}</p>
            {!! $chg($g['expenses'], false) !!}
        </div>
        <div class="bg-white rounded-xl shadow-card p-4 col-span-2 lg:col-span-1">
            <p class="text-xs text-gray-500">Net {{ $c['net'] >= 0 ? 'profit' : 'loss' }} <span class="text-gray-400">({{ $c['net_margin'] }}%)</span></p>
            <p class="text-xl font-bold {{ $c['net'] >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ $rs($c['net']) }}</p>
            {!! $chg($g['net']) !!}
        </div>
    </div>
    <p class="text-xs text-gray-500 -mt-3">Sales &minus; cost of goods = gross profit. Gross profit &minus; running expenses = net profit.</p>

    {{-- Recommendations --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
        <div class="bg-white rounded-xl shadow-card overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-200 bg-green-50"><h3 class="font-semibold text-green-800"><i class="fas fa-thumbs-up mr-2"></i> Going well</h3></div>
            <ul class="p-4 space-y-2 text-sm text-gray-700">
                @forelse($good as $t)<li class="flex gap-2"><i class="fas fa-check-circle text-green-500 mt-0.5"></i><span>{{ $t }}</span></li>
                @empty<li class="text-gray-400">Nothing to highlight yet.</li>@endforelse
            </ul>
        </div>
        <div class="bg-white rounded-xl shadow-card overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-200 bg-blue-50"><h3 class="font-semibold text-blue-800"><i class="fas fa-arrow-up-right-dots mr-2"></i> Improve</h3></div>
            <ul class="p-4 space-y-3 text-sm text-gray-700">
                @forelse($improve as [$title, $text])<li><p class="font-medium text-gray-900">{{ $title }}</p><p>{{ $text }}</p></li>
                @empty<li class="text-gray-400">No improvement points found.</li>@endforelse
            </ul>
        </div>
        <div class="bg-white rounded-xl shadow-card overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-200 bg-red-50"><h3 class="font-semibold text-red-800"><i class="fas fa-hand mr-2"></i> Control</h3></div>
            <ul class="p-4 space-y-3 text-sm text-gray-700">
                @forelse($control as [$title, $text])<li><p class="font-medium text-gray-900">{{ $title }}</p><p>{{ $text }}</p></li>
                @empty<li class="text-gray-400">Nothing needs tighter control.</li>@endforelse
            </ul>
        </div>
    </div>

    {{-- Charts --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
        <div class="bg-white rounded-xl shadow-card p-4 sm:p-6">
            <h3 class="font-semibold text-gray-900 mb-1">Sales, expenses and profit</h3>
            <p class="text-xs text-gray-500 mb-3">Is the green bar (sales) getting taller and the blue line (profit) going up?</p>
            <div class="h-72"><canvas id="trendChart"></canvas></div>
        </div>
        <div class="bg-white rounded-xl shadow-card p-4 sm:p-6">
            <h3 class="font-semibold text-gray-900 mb-1">Margins (%)</h3>
            <p class="text-xs text-gray-500 mb-3">How many rupees of every 100 sold you keep. Falling lines mean costs are eating profit.</p>
            <div class="h-72"><canvas id="marginChart"></canvas></div>
        </div>
        <div class="bg-white rounded-xl shadow-card p-4 sm:p-6">
            <h3 class="font-semibold text-gray-900 mb-1">This {{ $unit }} vs {{ $compareLabel }}</h3>
            <div class="h-72"><canvas id="compareChart"></canvas></div>
        </div>
        <div class="bg-white rounded-xl shadow-card p-4 sm:p-6">
            <h3 class="font-semibold text-gray-900 mb-1">Where the expense money went</h3>
            @if($expenseRows->count())
                <div class="h-72"><canvas id="expenseChart"></canvas></div>
            @else
                <p class="text-sm text-gray-400 py-16 text-center">No approved expenses this {{ $unit }}.</p>
            @endif
        </div>
    </div>

    {{-- Products + cash position --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
        <div class="bg-white rounded-xl shadow-card overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-200"><h3 class="font-semibold text-gray-900">Products that made the most profit</h3></div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs text-gray-500 uppercase"><th class="py-2 px-4">Product</th><th class="py-2 px-4 text-right">Sold</th><th class="py-2 px-4 text-right">Profit</th><th class="py-2 px-4 text-right">Margin</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                    @forelse($topProducts as $r)
                        <tr><td class="py-2 px-4">{{ $r->name }}</td><td class="py-2 px-4 text-right">{{ $rs($r->revenue) }}</td><td class="py-2 px-4 text-right {{ $r->profit >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ $rs($r->profit) }}</td><td class="py-2 px-4 text-right">{{ $r->margin }}%</td></tr>
                    @empty
                        <tr><td colspan="4" class="py-4 text-center text-gray-400">No sales this {{ $unit }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($lossProducts->count())
                <div class="px-5 py-3 border-t border-gray-200 bg-red-50 text-sm text-red-800">
                    <p class="font-medium mb-1"><i class="fas fa-triangle-exclamation mr-1"></i> Sold below cost:</p>
                    @foreach($lossProducts as $r)<p>{{ $r->name }} &mdash; lost {{ $rs(abs($r->profit)) }}</p>@endforeach
                </div>
            @endif
        </div>

        <div class="bg-white rounded-xl shadow-card overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-200"><h3 class="font-semibold text-gray-900">Cash side (profit is not the same as cash)</h3></div>
            <div class="p-5 space-y-3 text-sm">
                <div class="flex justify-between"><span class="text-gray-600">Sold this {{ $unit }} but not yet paid</span><span class="font-semibold text-orange-600">{{ $rs($uncollected) }} ({{ $uncollectedPct }}%)</span></div>
                <div class="flex justify-between"><span class="text-gray-600">Total customers owe you</span><a href="{{ route('admin.reports.receivable') }}" class="font-semibold text-blue-600 hover:underline">{{ $rs($totalReceivable) }}</a></div>
                <div class="flex justify-between"><span class="text-gray-600">Total you owe suppliers</span><a href="{{ route('admin.reports.payable') }}" class="font-semibold text-red-600 hover:underline">{{ $rs($totalPayable) }}</a></div>
                <div class="flex justify-between"><span class="text-gray-600">Sales returned this {{ $unit }}</span><span class="font-semibold">{{ $rs($returns) }} ({{ $returnRate }}%)</span></div>
                <p class="text-xs text-gray-400 pt-2 border-t border-gray-100">Figures follow the Profit &amp; Loss report exactly: confirmed/partial/paid sales minus returns, approved or paid expenses only. <a class="text-blue-600 hover:underline" href="{{ route('admin.reports.profit-loss', ['from_date' => $curFrom, 'to_date' => $curTo]) }}">Open full P&amp;L for this {{ $unit }}</a>.</p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;
    var trend = @json($trend);
    var labels = trend.map(function (t) { return t.label; });
    var k = function (v) { return 'Rs. ' + (v / 1000) + 'k'; };

    new Chart(document.getElementById('trendChart'), {
        type: 'bar',
        data: { labels: labels, datasets: [
            { label: 'Sales', data: trend.map(function (t) { return t.revenue; }), backgroundColor: 'rgba(34,197,94,.35)', borderColor: '#22c55e', borderWidth: 1, borderRadius: 4 },
            { label: 'Expenses', data: trend.map(function (t) { return t.expenses; }), backgroundColor: 'rgba(239,68,68,.3)', borderColor: '#ef4444', borderWidth: 1, borderRadius: 4 },
            { label: 'Net profit', type: 'line', data: trend.map(function (t) { return t.net; }), borderColor: '#2563eb', backgroundColor: '#2563eb', tension: .3 }
        ]},
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' } }, scales: { y: { ticks: { callback: k } } } }
    });

    new Chart(document.getElementById('marginChart'), {
        type: 'line',
        data: { labels: labels, datasets: [
            { label: 'Gross margin %', data: trend.map(function (t) { return t.gross_margin; }), borderColor: '#f59e0b', backgroundColor: '#f59e0b', tension: .3 },
            { label: 'Net margin %', data: trend.map(function (t) { return t.net_margin; }), borderColor: '#2563eb', backgroundColor: '#2563eb', tension: .3 }
        ]},
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' } }, scales: { y: { ticks: { callback: function (v) { return v + '%'; } } } } }
    });

    var cur = @json($c), prev = @json($p);
    new Chart(document.getElementById('compareChart'), {
        type: 'bar',
        data: { labels: ['Sales', 'Cost of goods', 'Gross profit', 'Expenses', 'Net profit'], datasets: [
            { label: @json($compareLabel), data: [prev.revenue, prev.cogs, prev.gross, prev.expenses, prev.net], backgroundColor: 'rgba(156,163,175,.6)', borderRadius: 4 },
            { label: 'This {{ $unit }}', data: [cur.revenue, cur.cogs, cur.gross, cur.expenses, cur.net], backgroundColor: 'rgba(37,99,235,.7)', borderRadius: 4 }
        ]},
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' } }, scales: { y: { ticks: { callback: k } } } }
    });

    @if($expenseRows->count())
    new Chart(document.getElementById('expenseChart'), {
        type: 'doughnut',
        data: { labels: @json($expenseRows->pluck('name')), datasets: [{ data: @json($expenseRows->pluck('total')), backgroundColor: ['#ef4444','#f97316','#f59e0b','#eab308','#84cc16','#14b8a6','#6366f1','#a855f7'] }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });
    @endif
});
</script>
@endpush

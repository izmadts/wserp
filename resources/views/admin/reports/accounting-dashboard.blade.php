@extends('layouts.admin')

@section('title', 'Accounting Dashboard')
@section('page-title', 'Accounting Dashboard')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-500">
            Current month ({{ date('d-M-Y', strtotime($currentFrom)) }} - {{ date('d-M-Y', strtotime($currentTo)) }}) vs. previous month.
        </p>
        <a href="{{ route('admin.reports.profit-loss') }}" class="text-sm text-blue-600 hover:underline">
            <i class="fas fa-file-invoice mr-1"></i> Full Profit & Loss Report
        </a>
    </div>

    <!-- Tiles -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
        <div class="bg-white rounded-xl shadow-card p-4 sm:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Revenue</p>
                    <p class="text-2xl font-bold text-green-600">Rs. {{ number_format($current['totalIncome'], 2) }}</p>
                    <p class="text-xs {{ $revenueGrowth >= 0 ? 'text-green-600' : 'text-red-600' }} mt-1">
                        <i class="fas {{ $revenueGrowth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' }} mr-1"></i>{{ number_format(abs($revenueGrowth), 1) }}% vs last month
                    </p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center"><i class="fas fa-arrow-up text-green-600 text-xl"></i></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-card p-4 sm:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">COGS</p>
                    <p class="text-2xl font-bold text-orange-600">Rs. {{ number_format($current['cogs'], 2) }}</p>
                </div>
                <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center"><i class="fas fa-box text-orange-600 text-xl"></i></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-card p-4 sm:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Gross Profit</p>
                    <p class="text-2xl font-bold {{ $current['grossProfit'] >= 0 ? 'text-blue-600' : 'text-red-600' }}">Rs. {{ number_format($current['grossProfit'], 2) }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center"><i class="fas fa-chart-line text-blue-600 text-xl"></i></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-card p-4 sm:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Operating Expenses</p>
                    <p class="text-2xl font-bold text-red-600">Rs. {{ number_format($current['operatingExpenses'], 2) }}</p>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center"><i class="fas fa-arrow-down text-red-600 text-xl"></i></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-card p-4 sm:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Net Profit / Loss</p>
                    <p class="text-2xl font-bold {{ $current['netProfit'] >= 0 ? 'text-green-600' : 'text-red-600' }}">Rs. {{ number_format($current['netProfit'], 2) }}</p>
                    <p class="text-xs {{ $netProfitGrowth >= 0 ? 'text-green-600' : 'text-red-600' }} mt-1">
                        <i class="fas {{ $netProfitGrowth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' }} mr-1"></i>{{ number_format(abs($netProfitGrowth), 1) }}% vs last month
                    </p>
                </div>
                <div class="w-12 h-12 {{ $current['netProfit'] >= 0 ? 'bg-green-100' : 'bg-red-100' }} rounded-xl flex items-center justify-center">
                    <i class="fas {{ $current['netProfit'] >= 0 ? 'fa-check-circle' : 'fa-exclamation-circle' }} {{ $current['netProfit'] >= 0 ? 'text-green-600' : 'text-red-600' }} text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-card p-4 sm:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Net Margin</p>
                    <p class="text-2xl font-bold {{ $netMargin >= 0 ? 'text-blue-600' : 'text-red-600' }}">{{ number_format($netMargin, 1) }}%</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center"><i class="fas fa-percentage text-blue-600 text-xl"></i></div>
            </div>
        </div>
    </div>

    <!-- Cash position -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
        <div class="bg-white rounded-xl shadow-card p-4 sm:p-6">
            <p class="text-sm font-medium text-gray-500">Cash on Hand</p>
            <p class="text-xl font-bold text-gray-900 mt-1">Rs. {{ number_format($cashBalance, 2) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-card p-4 sm:p-6">
            <p class="text-sm font-medium text-gray-500">Bank Balance</p>
            <p class="text-xl font-bold text-gray-900 mt-1">Rs. {{ number_format($bankBalance, 2) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-card p-4 sm:p-6">
            <p class="text-sm font-medium text-gray-500">Total Receivable</p>
            <a href="{{ route('admin.reports.receivable') }}" class="text-xl font-bold text-green-600 mt-1 block hover:underline">Rs. {{ number_format($totalReceivable, 2) }}</a>
        </div>
        <div class="bg-white rounded-xl shadow-card p-4 sm:p-6">
            <p class="text-sm font-medium text-gray-500">Total Payable</p>
            <a href="{{ route('admin.reports.payable') }}" class="text-xl font-bold text-red-600 mt-1 block hover:underline">Rs. {{ number_format($totalPayable, 2) }}</a>
        </div>
    </div>

    <!-- 6-month trend -->
    <div class="bg-white rounded-xl shadow-card overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900"><i class="fas fa-chart-bar text-blue-600 mr-2"></i> 6-Month Trend</h3>
        </div>
        <div class="p-6">
            <div style="height: 300px; position: relative;">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Category breakdowns -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-card overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900"><i class="fas fa-arrow-down text-red-600 mr-2"></i> Expenses by Category</h3>
            </div>
            <div class="p-6">
                @if($current['expensesByCategory']->count() > 0)
                <div style="height: 260px; position: relative;">
                    <canvas id="expenseCategoryChart"></canvas>
                </div>
                @else
                <p class="text-center text-gray-500 py-8">No expenses this month</p>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-card overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900"><i class="fas fa-arrow-up text-green-600 mr-2"></i> Income by Category</h3>
            </div>
            <div class="p-6">
                @if($current['incomeByCategory']->count() > 0)
                <div style="height: 260px; position: relative;">
                    <canvas id="incomeCategoryChart"></canvas>
                </div>
                @else
                <p class="text-center text-gray-500 py-8">No other income this month</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var trendCtx = document.getElementById('trendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'bar',
        data: {
            labels: @json(array_column($monthlyTrend, 'month')),
            datasets: [
                { label: 'Revenue', data: @json(array_column($monthlyTrend, 'revenue')), backgroundColor: 'rgba(34, 197, 94, 0.2)', borderColor: 'rgba(34, 197, 94, 1)', borderWidth: 2, borderRadius: 4 },
                { label: 'Expenses', data: @json(array_column($monthlyTrend, 'expenses')), backgroundColor: 'rgba(239, 68, 68, 0.2)', borderColor: 'rgba(239, 68, 68, 1)', borderWidth: 2, borderRadius: 4 },
                { label: 'Net Profit', data: @json(array_column($monthlyTrend, 'profit')), type: 'line', backgroundColor: 'rgba(59, 130, 246, 0.2)', borderColor: 'rgba(59, 130, 246, 1)', borderWidth: 2, tension: 0.3 },
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: { y: { beginAtZero: true, ticks: { callback: function (value) { return 'Rs. ' + (value / 1000) + 'k'; } } } }
        }
    });

    @if($current['expensesByCategory']->count() > 0)
    new Chart(document.getElementById('expenseCategoryChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: @json($current['expensesByCategory']->map(fn($c) => $c->category->name ?? 'Uncategorized')),
            datasets: [{
                data: @json($current['expensesByCategory']->pluck('total')),
                backgroundColor: ['#ef4444', '#f97316', '#f59e0b', '#eab308', '#84cc16', '#14b8a6', '#6366f1', '#a855f7'],
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });
    @endif

    @if($current['incomeByCategory']->count() > 0)
    new Chart(document.getElementById('incomeCategoryChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: @json($current['incomeByCategory']->map(fn($c) => $c->category->name ?? 'Uncategorized')),
            datasets: [{
                data: @json($current['incomeByCategory']->pluck('total')),
                backgroundColor: ['#22c55e', '#10b981', '#14b8a6', '#06b6d4', '#3b82f6', '#6366f1', '#8b5cf6', '#a855f7'],
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });
    @endif
});
</script>
@endpush

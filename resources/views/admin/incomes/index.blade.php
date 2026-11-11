@extends('layouts.admin')

@section('title', 'Income')
@section('page-title', 'Income Management')

@section('content')
<div class="space-y-6">

    {{-- Summary cards. Values are filled in by the script below from
         whatever rows the search box currently leaves visible. --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
        <div class="bg-white rounded-xl shadow-card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Income</p>
                    <p class="text-2xl font-bold text-gray-900" data-card="total">Rs. 0.00</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-arrow-up text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">This Month</p>
                    <p class="text-2xl font-bold text-blue-600" data-card="month">Rs. 0.00</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-calendar-alt text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Today</p>
                    <p class="text-2xl font-bold text-purple-600" data-card="today">Rs. 0.00</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-clock text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Transactions</p>
                    <p class="text-2xl font-bold text-gray-900" data-card="count">0</p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-file-invoice text-yellow-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Income Table -->
    <div class="bg-white rounded-xl shadow-card overflow-hidden">
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
            <div>
                <span class="text-sm font-medium text-gray-700">
                    <i class="fas fa-list text-gray-400 mr-2"></i> All Income
                </span>
                <span class="ml-2 text-sm text-gray-500">{{ $incomes->count() }} total</span>
            </div>
            <a href="{{ route('admin.incomes.create') }}" 
               class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition-colors duration-200">
                <i class="fas fa-plus mr-1"></i> Add Income
            </a>
        </div>

        <div class="p-4 sm:p-6">
            <div class="overflow-x-auto">
                <table class="w-full" id="incomesTable">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">#</th>
                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Title</th>
                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Category</th>
                            <th class="text-right text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Amount</th>
                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Date</th>
                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Method</th>
                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Source</th>
                            <th class="text-center text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($incomes as $income)
                        <tr class="hover:bg-gray-50 transition-colors duration-150" data-amount="{{ (float) $income->amount }}" data-date="{{ $income->income_date->format('Y-m-d') }}">
                            <td class="py-3 px-2">
                                <code class="text-xs bg-gray-100 px-2 py-1 rounded">{{ $income->income_no }}</code>
                            </td>
                            <td class="py-3 px-2">
                                <span class="font-medium text-gray-900">{{ $income->title }}</span>
                            </td>
                            <td class="py-3 px-2 text-sm text-gray-600">
                                {{ $income->category->name ?? '-' }}
                            </td>
                            <td class="py-3 px-2 text-right font-medium text-green-600">
                                Rs. {{ number_format($income->amount, 2) }}
                            </td>
                            <td class="py-3 px-2 text-sm text-gray-600">
                                {{ $income->income_date->format('d-m-Y') }}
                            </td>
                            <td class="py-3 px-2 text-sm text-gray-600">
                                {{ $income->payment_method_label }}
                            </td>
                            <td class="py-3 px-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ $income->source_label }}
                                </span>
                            </td>
                            <td class="py-3 px-2 text-center">
                                <div class="flex items-center justify-center space-x-1">
                                    <a href="{{ route('admin.incomes.show', $income) }}" 
                                       class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors duration-200">
                                        <i class="fas fa-eye text-sm"></i>
                                    </a>
                                    <a href="{{ route('admin.incomes.edit', $income) }}" 
                                       class="p-1.5 text-yellow-600 hover:bg-yellow-50 rounded-lg transition-colors duration-200">
                                        <i class="fas fa-edit text-sm"></i>
                                    </a>
                                    <form action="{{ route('admin.incomes.destroy', $income) }}" method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" onclick="return confirm('Are you sure?')" 
                                                class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition-colors duration-200">
                                            <i class="fas fa-trash text-sm"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const tableEl = document.getElementById('incomesTable');
        if (!tableEl) return;

        const table = new DataTable('#incomesTable', {
            pageLength: 25,
            responsive: true,
            order: [[4, 'desc']],
            language: {
                search: "Search:",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ entries"
            }
        });

        const money = n => 'Rs. ' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const card = (key, value) => {
            const el = document.querySelector('[data-card="' + key + '"]');
            if (el) el.textContent = value;
        };

        const now = new Date();
        const todayStr = now.toISOString().slice(0, 10);
        const curYear = now.getFullYear();
        const curMonth = now.getMonth(); // 0-based

        function refreshCards() {
            const rows = table.rows({ search: 'applied' }).nodes().toArray();
            let total = 0, month = 0, today = 0;

            rows.forEach(tr => {
                const amount = parseFloat(tr.dataset.amount) || 0;
                const dateStr = tr.dataset.date; // Y-m-d
                total += amount;
                if (dateStr === todayStr) today += amount;
                if (dateStr) {
                    const d = new Date(dateStr + 'T00:00:00');
                    if (d.getFullYear() === curYear && d.getMonth() === curMonth) month += amount;
                }
            });

            card('total', money(total));
            card('month', money(month));
            card('today', money(today));
            card('count', rows.length.toLocaleString('en-US'));
        }

        table.on('draw', refreshCards);
        refreshCards();
    });
</script>
@endpush
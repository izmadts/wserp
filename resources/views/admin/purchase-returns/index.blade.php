@extends('layouts.admin')

@section('title', 'Purchase Returns')
@section('page-title', 'Purchase Returns Management')

@section('content')
<div class="space-y-6">

{{-- Summary cards. Filled in by the script below from whatever rows the
     search box currently leaves visible. --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
    <div class="bg-white rounded-xl shadow-card p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Returns</p>
                <p class="text-2xl font-bold text-gray-900" data-card="count">0</p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center"><i class="fas fa-undo-alt text-blue-600 text-xl"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-card p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Total Returned</p>
                <p class="text-2xl font-bold text-red-600" data-card="total">Rs. 0.00</p>
            </div>
            <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center"><i class="fas fa-money-bill-wave text-red-600 text-xl"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-card p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">This Month</p>
                <p class="text-2xl font-bold text-yellow-600" data-card="month">Rs. 0.00</p>
            </div>
            <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center"><i class="fas fa-calendar-alt text-yellow-600 text-xl"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-card p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Avg. Return</p>
                <p class="text-2xl font-bold text-gray-900" data-card="avg">Rs. 0.00</p>
            </div>
            <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center"><i class="fas fa-chart-bar text-purple-600 text-xl"></i></div>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-card overflow-hidden">
    <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
        <div>
            <span class="text-sm font-medium text-gray-700">
                <i class="fas fa-undo-alt text-gray-400 mr-2"></i> All Purchase Returns
            </span>
            <span class="ml-2 text-sm text-gray-500">{{ $returns->count() }} total</span>
        </div>
        <a href="{{ route('admin.purchase-returns.create') }}" 
           class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors duration-200">
            <i class="fas fa-plus mr-1"></i> New Return
        </a>
    </div>
    
    <div class="p-4 sm:p-6">
        <div class="overflow-x-auto">
            <table class="w-full" id="returnsTable">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Return No</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">PO #</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Supplier</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Date</th>
                        <th class="text-right text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Amount</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Refund Method</th>
                        <th class="text-center text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($returns as $return)
                    <tr class="hover:bg-gray-50 transition-colors duration-150" data-amount="{{ (float) $return->total_amount }}" data-date="{{ $return->return_date->format('Y-m-d') }}">
                        <td class="py-3 px-2">
                            <code class="text-xs bg-gray-100 px-2 py-1 rounded">{{ $return->return_no }}</code>
                        </td>
                        <td class="py-3 px-2">
                            <a href="{{ route('admin.purchases.show', $return->purchase) }}" class="text-blue-600 hover:underline text-sm">
                                {{ $return->purchase->invoice_no ?? 'N/A' }}
                            </a>
                        </td>
                        <td class="py-3 px-2">
                            <span class="font-medium text-gray-900">{{ $return->supplier->name ?? 'N/A' }}</span>
                        </td>
                        <td class="py-3 px-2 text-sm text-gray-600">
                            {{ $return->return_date->format('d-m-Y') }}
                        </td>
                        <td class="py-3 px-2 text-right font-medium text-red-600">
                            Rs. {{ number_format($return->total_amount, 2) }}
                        </td>
                        <td class="py-3 px-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                {{ $return->refund_method_label }}
                            </span>
                        </td>
                        <td class="py-3 px-2 text-center">
                            <div class="flex items-center justify-center space-x-1">
                                <a href="{{ route('admin.purchase-returns.show', $return) }}" 
                                   class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors duration-200">
                                    <i class="fas fa-eye text-sm"></i>
                                </a>
                                <form action="{{ route('admin.purchase-returns.destroy', $return) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" onclick="return confirm('Are you sure? This will reverse the return.')" 
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
        const tableEl = document.getElementById('returnsTable');
        if (!tableEl) return;

        const table = new DataTable('#returnsTable', {
            pageLength: 25,
            responsive: true,
            order: [[3, 'desc']],
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
        const curYear = now.getFullYear();
        const curMonth = now.getMonth();

        function refreshCards() {
            const rows = table.rows({ search: 'applied' }).nodes().toArray();
            let total = 0, month = 0;
            rows.forEach(tr => {
                const amount = parseFloat(tr.dataset.amount) || 0;
                total += amount;
                const dateStr = tr.dataset.date;
                if (dateStr) {
                    const d = new Date(dateStr + 'T00:00:00');
                    if (d.getFullYear() === curYear && d.getMonth() === curMonth) month += amount;
                }
            });
            const n = rows.length;
            card('count', n.toLocaleString('en-US'));
            card('total', money(total));
            card('month', money(month));
            card('avg', money(n ? total / n : 0));
        }

        table.on('draw', refreshCards);
        refreshCards();
    });
</script>
@endpush
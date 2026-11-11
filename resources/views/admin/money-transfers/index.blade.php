@extends('layouts.admin')

@section('title', 'Money Transfers')
@section('page-title', 'Money Transfer Management')

@section('content')
<div class="space-y-6">

    {{-- Summary cards. Values are filled in by the script below from
         whatever rows the search box currently leaves visible. --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6" id="transferCards">
        <div class="bg-white rounded-xl shadow-card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Transferred</p>
                    <p class="text-2xl font-bold text-gray-900" data-card="total">Rs. 0.00</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-exchange-alt text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Pending</p>
                    <p class="text-2xl font-bold text-yellow-600" data-card="pending">Rs. 0.00</p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-clock text-yellow-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Transactions</p>
                    <p class="text-2xl font-bold text-gray-900" data-card="count">0</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-list text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Completed</p>
                    <p class="text-2xl font-bold text-green-600" data-card="completed">0</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Transfers Table -->
    <div class="bg-white rounded-xl shadow-card overflow-hidden">
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
            <div>
                <span class="text-sm font-medium text-gray-700">
                    <i class="fas fa-list text-gray-400 mr-2"></i> All Transfers
                </span>
                <span class="ml-2 text-sm text-gray-500">{{ $transfers->count() }} total</span>
            </div>
            <a href="{{ route('admin.money-transfers.create') }}" 
               class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors duration-200">
                <i class="fas fa-plus mr-1"></i> New Transfer
            </a>
        </div>

        <div class="p-4 sm:p-6">
            <div class="overflow-x-auto">
                <table class="w-full" id="transfersTable">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">#</th>
                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">From</th>
                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">To</th>
                            <th class="text-right text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Amount</th>
                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Date</th>
                            <th class="text-center text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Status</th>
                            <th class="text-center text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($transfers as $transfer)
                        <tr class="hover:bg-gray-50 transition-colors duration-150" data-amount="{{ (float) $transfer->amount }}" data-status="{{ $transfer->status }}">
                            <td class="py-3 px-2">
                                <code class="text-xs bg-gray-100 px-2 py-1 rounded">{{ $transfer->transfer_no }}</code>
                            </td>
                            <td class="py-3 px-2 text-sm text-gray-600">
                                {{ $transfer->fromAccount->name ?? '-' }}
                            </td>
                            <td class="py-3 px-2 text-sm text-gray-600">
                                {{ $transfer->toAccount->name ?? '-' }}
                            </td>
                            <td class="py-3 px-2 text-right font-medium text-blue-600">
                                Rs. {{ number_format($transfer->amount, 2) }}
                            </td>
                            <td class="py-3 px-2 text-sm text-gray-600">
                                {{ $transfer->transfer_date->format('d-m-Y') }}
                            </td>
                            <td class="py-3 px-2 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $transfer->status_color }}">
                                    {{ $transfer->status_label }}
                                </span>
                            </td>
                            <td class="py-3 px-2 text-center">
                                <div class="flex items-center justify-center space-x-1">
                                    <a href="{{ route('admin.money-transfers.show', $transfer) }}" 
                                       class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors duration-200">
                                        <i class="fas fa-eye text-sm"></i>
                                    </a>
                                    @if($transfer->status == 'pending')
                                    <a href="{{ route('admin.money-transfers.edit', $transfer) }}" 
                                       class="p-1.5 text-yellow-600 hover:bg-yellow-50 rounded-lg transition-colors duration-200">
                                        <i class="fas fa-edit text-sm"></i>
                                    </a>
                                    <form action="{{ route('admin.money-transfers.complete', $transfer) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" onclick="return confirm('Complete this transfer?')" 
                                                class="p-1.5 text-green-600 hover:bg-green-50 rounded-lg transition-colors duration-200">
                                            <i class="fas fa-check text-sm"></i>
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.money-transfers.cancel', $transfer) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" onclick="return confirm('Cancel this transfer?')" 
                                                class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition-colors duration-200">
                                            <i class="fas fa-times text-sm"></i>
                                        </button>
                                    </form>
                                    @endif
                                    <form action="{{ route('admin.money-transfers.destroy', $transfer) }}" method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" onclick="return confirm('Delete this transfer?')" 
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
        const tableEl = document.getElementById('transfersTable');
        if (!tableEl) return;

        const table = new DataTable('#transfersTable', {
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

        function refreshCards() {
            const rows = table.rows({ search: 'applied' }).nodes().toArray();
            let total = 0, pending = 0, completed = 0;

            rows.forEach(tr => {
                const amount = parseFloat(tr.dataset.amount) || 0;
                const status = tr.dataset.status;
                if (status === 'completed') { total += amount; completed++; }
                if (status === 'pending') pending += amount;
            });

            card('total', money(total));
            card('pending', money(pending));
            card('count', rows.length.toLocaleString('en-US'));
            card('completed', completed.toLocaleString('en-US'));
        }

        table.on('draw', refreshCards);
        refreshCards();
    });
</script>
@endpush
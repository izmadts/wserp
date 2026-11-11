@extends('layouts.admin')

@section('title', 'Suppliers')
@section('page-title', 'Supplier Management')

@section('content')
<div class="space-y-6">

{{-- Summary cards. Filled in by the script below from whatever rows the
     search box currently leaves visible. --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
    <div class="bg-white rounded-xl shadow-card p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Suppliers</p>
                <p class="text-2xl font-bold text-gray-900" data-card="count">0</p>
                <p class="text-xs text-gray-500 mt-1" data-card="count-sub">&nbsp;</p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center"><i class="fas fa-truck text-blue-600 text-xl"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-card p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Total Payable</p>
                <p class="text-2xl font-bold text-red-600" data-card="payable">Rs. 0.00</p>
                <p class="text-xs text-gray-500 mt-1" data-card="payable-sub">&nbsp;</p>
            </div>
            <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center"><i class="fas fa-hand-holding-usd text-red-600 text-xl"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-card p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Advance / Overpaid</p>
                <p class="text-2xl font-bold text-green-600" data-card="advance">Rs. 0.00</p>
                <p class="text-xs text-gray-500 mt-1" data-card="advance-sub">&nbsp;</p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center"><i class="fas fa-piggy-bank text-green-600 text-xl"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-card p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Total Purchases</p>
                <p class="text-2xl font-bold text-gray-900" data-card="purchases">0</p>
                <p class="text-xs text-gray-500 mt-1" data-card="purchases-sub">&nbsp;</p>
            </div>
            <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center"><i class="fas fa-shopping-cart text-purple-600 text-xl"></i></div>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-card overflow-hidden">
    <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
        <div>
            <span class="text-sm font-medium text-gray-700">
                <i class="fas fa-truck text-gray-400 mr-2"></i> All Suppliers
            </span>
            <span class="ml-2 text-sm text-gray-500">{{ $suppliers->count() }} total</span>
        </div>
        <a href="{{ route('admin.suppliers.create') }}"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors duration-200">
            <i class="fas fa-plus mr-1"></i> Add Supplier
        </a>
    </div>

    <div class="p-4 sm:p-6">
        <div class="overflow-x-auto">
            <table class="w-full" id="suppliersTable">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Code</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Name</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Email</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Phone</th>
                        <th class="text-right text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Balance</th>
                        <th class="text-center text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Purchases</th>
                        <th class="text-center text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Status</th>
                        <th class="text-center text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($suppliers as $supplier)
                    <tr class="hover:bg-gray-50 transition-colors duration-150" data-balance="{{ (float) $supplier->balance }}" data-purchases="{{ (int) $supplier->purchases_count }}" data-active="{{ $supplier->is_active ? 1 : 0 }}">
                        <td class="py-3 px-2">
                            <code class="text-xs bg-gray-100 px-2 py-1 rounded">{{ $supplier->code }}</code>
                        </td>
                        <td class="py-3 px-2">
                            <span class="font-medium text-gray-900">{{ $supplier->name }}</span>
                        </td>
                        <td class="py-3 px-2 text-sm text-gray-600">{{ $supplier->email ?? '-' }}</td>
                        <td class="py-3 px-2 text-sm text-gray-600">{{ $supplier->phone ?? '-' }}</td>
                        <td class="py-3 px-2 text-right font-medium {{ $supplier->balance > 0 ? 'text-red-600' : 'text-green-600' }}">
                            {{ $supplier->formatted_balance }}
                        </td>
                        <td class="py-3 px-2 text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                {{ $supplier->purchases_count }}
                            </span>
                        </td>
                        <td class="py-3 px-2 text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $supplier->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                {{ $supplier->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="py-3 px-2 text-center">
                            <div class="flex items-center justify-center space-x-1">
                                <a href="{{ route('admin.suppliers.show', $supplier) }}"
                                    class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors duration-200">
                                    <i class="fas fa-eye text-sm"></i>
                                </a>
                                <a href="{{ route('admin.suppliers.edit', $supplier) }}"
                                    class="p-1.5 text-yellow-600 hover:bg-yellow-50 rounded-lg transition-colors duration-200">
                                    <i class="fas fa-edit text-sm"></i>
                                </a>
                                <form action="{{ route('admin.suppliers.toggle-status', $supplier) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="p-1.5 {{ $supplier->is_active ? 'text-gray-600 hover:bg-gray-100' : 'text-green-600 hover:bg-green-50' }} rounded-lg transition-colors duration-200">
                                        <i class="fas {{ $supplier->is_active ? 'fa-pause' : 'fa-play' }} text-sm"></i>
                                    </button>
                                </form>
                                <form action="{{ route('admin.suppliers.destroy', $supplier) }}" method="POST" class="inline">
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
        const tableEl = document.getElementById('suppliersTable');
        if (!tableEl) return;

        const table = new DataTable('#suppliersTable', {
            pageLength: 25,
            responsive: true,
            order: [
                [1, 'asc']
            ],
            language: {
                search: "Search:",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ entries"
            }
        });

        const money = n => 'Rs. ' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const card = (key, value, sub) => {
            const v = document.querySelector('[data-card="' + key + '"]');
            const s = document.querySelector('[data-card="' + key + '-sub"]');
            if (v) v.textContent = value;
            if (s) s.textContent = sub || ' ';
        };

        function refreshCards() {
            const rows = table.rows({ search: 'applied' }).nodes().toArray();
            let active = 0, payable = 0, payableN = 0, advance = 0, advanceN = 0, purchases = 0;

            rows.forEach(tr => {
                const balance = parseFloat(tr.dataset.balance) || 0;
                if (tr.dataset.active === '1') active++;
                purchases += parseInt(tr.dataset.purchases, 10) || 0;
                if (balance > 0.005) { payable += balance; payableN++; }
                else if (balance < -0.005) { advance += -balance; advanceN++; }
            });

            const n = rows.length;
            card('count', n.toLocaleString('en-US'), active + ' active · ' + (n - active) + ' inactive');
            card('payable', money(payable), payableN ? payableN + ' supplier(s) owed' : 'Nobody is owed anything');
            card('advance', money(advance), advanceN ? advanceN + ' supplier(s)' : 'No advance payments');
            card('purchases', purchases.toLocaleString('en-US'), 'across ' + n + ' supplier(s) shown');
        }

        table.on('draw', refreshCards);
        refreshCards();
    });
</script>
@endpush
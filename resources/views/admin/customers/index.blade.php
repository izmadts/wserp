@extends('layouts.admin')

@section('title', 'Customers')
@section('page-title', 'Customer Management')

@section('content')
{{-- 12 columns don't fit beside the sidebar on a laptop screen, so the table scrolls
     sideways - but the row Actions stay pinned to the right edge, always reachable. --}}
<style>
    #customersTable th.cust-actions,
    #customersTable td.cust-actions {
        position: sticky;
        right: 0;
        z-index: 1;
        background: var(--color-white, #fff);
        box-shadow: -8px 0 8px -8px rgba(0, 0, 0, .2);
    }
    #customersTable tbody tr:hover td.cust-actions { background: var(--color-gray-50, #f9fafb); }
</style>
<div class="space-y-6">

    {{-- Summary cards. Values are filled in by the script below from whatever
         rows the filters/search currently leave visible, so they always
         describe exactly what the table is showing. --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6" id="customerCards">
        <div class="bg-white rounded-xl shadow-card p-4">
            <div class="flex items-center justify-between">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-500">Customers</p>
                    <p class="text-2xl font-bold text-gray-900" data-card="count">{{ $customers->count() }}</p>
                    <p class="text-xs text-gray-500 mt-1" data-card="count-sub">&nbsp;</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-users text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-card p-4">
            <div class="flex items-center justify-between">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-500">Total Receivable (Due)</p>
                    <p class="text-2xl font-bold text-red-600" data-card="due">Rs. 0.00</p>
                    <p class="text-xs text-gray-500 mt-1" data-card="due-sub">&nbsp;</p>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-hand-holding-usd text-red-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-card p-4">
            <div class="flex items-center justify-between">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-500">Advance / Extra Received</p>
                    <p class="text-2xl font-bold text-green-600" data-card="advance">Rs. 0.00</p>
                    <p class="text-xs text-gray-500 mt-1" data-card="advance-sub">&nbsp;</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-piggy-bank text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-card p-4">
            <div class="flex items-center justify-between">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-500">Total Sales</p>
                    <p class="text-2xl font-bold text-blue-600" data-card="sales">Rs. 0.00</p>
                    <p class="text-xs text-gray-500 mt-1" data-card="sales-sub">&nbsp;</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-shopping-bag text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-card p-4">
            <div class="flex items-center justify-between">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-500">Active in Last 30 Days</p>
                    <p class="text-2xl font-bold text-green-600" data-card="recent">0</p>
                    <p class="text-xs text-gray-500 mt-1" data-card="recent-sub">&nbsp;</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-bolt text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-card p-4">
            <div class="flex items-center justify-between">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-500">No Activity 90+ Days</p>
                    <p class="text-2xl font-bold text-yellow-600" data-card="dormant">0</p>
                    <p class="text-xs text-gray-500 mt-1" data-card="dormant-sub">&nbsp;</p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-bed text-yellow-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-card p-4">
            <div class="flex items-center justify-between">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-500">Over Credit Limit</p>
                    <p class="text-2xl font-bold text-red-600" data-card="overlimit">0</p>
                    <p class="text-xs text-gray-500 mt-1" data-card="overlimit-sub">&nbsp;</p>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-card p-4">
            <div class="flex items-center justify-between">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-500">Top Debtor</p>
                    <p class="text-2xl font-bold text-gray-900" data-card="top">Rs. 0.00</p>
                    <p class="text-xs text-gray-500 mt-1 truncate" data-card="top-sub">&nbsp;</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-user-clock text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-card overflow-hidden">
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
            <div>
                <span class="text-sm font-medium text-gray-700">
                    <i class="fas fa-users text-gray-400 mr-2"></i> All Customers
                </span>
                <span class="ml-2 text-sm text-gray-500">
                    <span id="customersShown">{{ $customers->count() }}</span> of {{ $customers->count() }} shown
                </span>
            </div>
            <a href="{{ route('admin.customers.create') }}"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors duration-200">
                <i class="fas fa-plus mr-1"></i> Add Customer
            </a>
        </div>

        {{-- Filters --}}
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200 bg-gray-50" id="customerFilters">
            <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-5 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1" for="fArea">Area / City</label>
                    <select id="fArea" data-filter="area" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">All areas</option>
                        @foreach($areas as $area)
                            <option value="{{ mb_strtolower($area) }}">{{ $area }}</option>
                        @endforeach
                        <option value="__none__">No area set</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1" for="fAgent">Agent</label>
                    <select id="fAgent" data-filter="agent" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">All agents</option>
                        @foreach($agents as $agent)
                            <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                        @endforeach
                        <option value="0">No agent (direct)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1" for="fDue">Due</label>
                    <select id="fDue" data-filter="due" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">All balances</option>
                        <option value="due">With due (owes us)</option>
                        <option value="clear">Settled (no due)</option>
                        <option value="advance">Advance / extra paid</option>
                        <option value="overlimit">Over credit limit</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1" for="fActivity">Recent activity</label>
                    <select id="fActivity" data-filter="activity" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">Any time</option>
                        <option value="7">Active in last 7 days</option>
                        <option value="30">Active in last 30 days</option>
                        <option value="90">Active in last 90 days</option>
                        <option value="idle30">No activity for 30+ days</option>
                        <option value="idle90">No activity for 90+ days</option>
                        <option value="never">Never active</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1" for="fStatus">Status</label>
                    <select id="fStatus" data-filter="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1" for="fType">Type</label>
                    <select id="fType" data-filter="type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">All types</option>
                        <option value="wholesale">Wholesale</option>
                        <option value="retail">Retail</option>
                        <option value="none">Not set</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1" for="fMin">Balance from (Rs.)</label>
                    <input type="number" step="any" id="fMin" data-filter="min" placeholder="e.g. 1000" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1" for="fMax">Balance up to (Rs.)</label>
                    <input type="number" step="any" id="fMax" data-filter="max" placeholder="e.g. 50000" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div class="flex items-end">
                    <button type="button" id="resetCustomerFilters"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-100 transition-colors duration-200">
                        <i class="fas fa-undo mr-1"></i> Reset filters
                    </button>
                </div>
            </div>
        </div>

        <div class="p-4 sm:p-6">
            <div class="overflow-x-auto">
                <table class="w-full" id="customersTable">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Code</th>
                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Name</th>
                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Email</th>
                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Mobile</th>
                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Area</th>
                            <th class="text-center text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Type</th>
                            <th class="text-right text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Balance</th>
                            <th class="text-center text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Sales</th>
                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Last Activity</th>
                            <th class="text-center text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Status</th>
                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Agent</th>
                            <th class="cust-actions text-center text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($customers as $customer)
                        @php
                            $stat = $stats[$customer->id] ?? ['total_sales' => 0, 'total_paid' => 0, 'balance' => (float) $customer->opening_balance, 'last_activity' => null];
                            $balance = (float) $stat['balance'];
                            $last = $stat['last_activity'];
                            // Whole days since the last invoice/payment, or -1 for "never".
                            $days = $last ? max(0, (int) floor((now()->startOfDay()->timestamp - $last->copy()->startOfDay()->timestamp) / 86400)) : -1;
                            $city = trim((string) $customer->city);
                            $group = $customer->customerGroup
                                ? ($customer->customerGroup->price_field == 'wholesale_price' ? 'wholesale' : 'retail')
                                : 'none';
                            $creditLimit = (float) $customer->credit_limit;
                        @endphp
                        <tr class="hover:bg-gray-50 transition-colors duration-150"
                            data-name="{{ $customer->name }}"
                            data-status="{{ $customer->is_active ? 'active' : 'inactive' }}"
                            data-agent="{{ $customer->created_by_agent_id ?? 0 }}"
                            data-city="{{ $city === '' ? '__none__' : mb_strtolower($city) }}"
                            data-group="{{ $group }}"
                            data-balance="{{ $balance }}"
                            data-sales="{{ $stat['total_sales'] }}"
                            data-paid="{{ $stat['total_paid'] }}"
                            data-credit="{{ $creditLimit }}"
                            data-days="{{ $days }}">
                            <td class="py-3 px-2 whitespace-nowrap">
                                <code class="text-xs bg-gray-100 px-2 py-1 rounded">{{ $customer->code }}</code>
                            </td>
                            <td class="py-3 px-2" style="min-width: 11rem">
                                <span class="font-medium text-gray-900">{{ $customer->name }}</span>
                            </td>
                            <td class="py-3 px-2 text-sm text-gray-600" style="max-width: 11rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $customer->email }}">{{ $customer->email ?? '-' }}</td>
                            <td class="py-3 px-2 text-sm text-gray-600 whitespace-nowrap">{{ $customer->mobile ?? '-' }}</td>
                            <td class="py-3 px-2 text-sm text-gray-600 whitespace-nowrap">{{ $city !== '' ? $city : '-' }}</td>
                            <td class="py-3 px-2 text-center">
                                @if($customer->customerGroup)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $group == 'wholesale' ? 'bg-purple-100 text-purple-800' : 'bg-teal-100 text-teal-800' }}">
                                        {{ $group == 'wholesale' ? 'Wholesale' : 'Retail' }}
                                    </span>
                                @else
                                    <span class="text-xs text-gray-400">-</span>
                                @endif
                            </td>
                            {{-- data-order = the real signed number, so the column sorts by
                                 amount (not by the "Rs. 1,000.00" / "+Rs. .. (Extra)" text). --}}
                            <td class="py-3 px-2 text-right font-medium whitespace-nowrap {{ $balance > 0 ? 'text-red-600' : 'text-green-600' }}" data-order="{{ $balance }}">
                                {{ $customer->formatted_balance }}
                            </td>
                            <td class="py-3 px-2 text-center" data-order="{{ $customer->sales_count }}">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ $customer->sales_count }}
                                </span>
                            </td>
                            <td class="py-3 px-2 text-sm text-gray-600 whitespace-nowrap" data-order="{{ $last ? $last->copy()->startOfDay()->timestamp : 0 }}">
                                @if($last)
                                    {{ $last->format('d-m-Y') }}
                                    <span class="block text-xs text-gray-400">{{ $days === 0 ? 'Today' : ($days === 1 ? 'Yesterday' : $days . ' days ago') }}</span>
                                @else
                                    <span class="text-xs text-gray-400">Never</span>
                                @endif
                            </td>
                            <td class="py-3 px-2 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $customer->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $customer->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="py-3 px-2 text-sm text-gray-600">
                                {{ $customer->createdByAgent->name ?? '-' }}
                            </td>
                            <td class="cust-actions py-3 px-2 text-center">
                                <div class="flex items-center justify-center space-x-1">
                                    <a href="{{ route('admin.customers.show', $customer) }}"
                                        class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors duration-200">
                                        <i class="fas fa-eye text-sm"></i>
                                    </a>
                                    <a href="{{ route('admin.customers.edit', $customer) }}"
                                        class="p-1.5 text-yellow-600 hover:bg-yellow-50 rounded-lg transition-colors duration-200">
                                        <i class="fas fa-edit text-sm"></i>
                                    </a>
                                    <form action="{{ route('admin.customers.toggle-status', $customer) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="p-1.5 {{ $customer->is_active ? 'text-gray-600 hover:bg-gray-100' : 'text-green-600 hover:bg-green-50' }} rounded-lg transition-colors duration-200">
                                            <i class="fas {{ $customer->is_active ? 'fa-pause' : 'fa-play' }} text-sm"></i>
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.customers.destroy', $customer) }}" method="POST" class="inline">
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
        const tableEl = document.getElementById('customersTable');
        if (!tableEl) return;

        const table = $(tableEl).DataTable({
            pageLength: 25,
            order: [[1, 'asc']],
            columnDefs: [
                // Balance / Sales / Last Activity carry a numeric data-order.
                { targets: [6, 7, 8], type: 'num' },
                { targets: [11], orderable: false, searchable: false },
            ],
            language: {
                search: "Search:",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ entries"
            }
        });

        // ---- Filters -------------------------------------------------------
        const controls = Array.from(document.querySelectorAll('#customerFilters [data-filter]'));
        const filters = {};
        controls.forEach(el => filters[el.dataset.filter] = '');

        function rowPasses(d) {
            const balance = parseFloat(d.balance) || 0;
            const days = parseInt(d.days, 10);           // -1 = never
            const credit = parseFloat(d.credit) || 0;

            if (filters.area && d.city !== filters.area) return false;
            if (filters.agent !== '' && d.agent !== filters.agent) return false;
            if (filters.status && d.status !== filters.status) return false;
            if (filters.type && d.group !== filters.type) return false;

            switch (filters.due) {
                case 'due':       if (!(balance > 0.005)) return false; break;
                case 'clear':     if (Math.abs(balance) > 0.005) return false; break;
                case 'advance':   if (!(balance < -0.005)) return false; break;
                case 'overlimit': if (!(credit > 0 && balance > credit + 0.005)) return false; break;
            }

            switch (filters.activity) {
                case '7': case '30': case '90':
                    if (days < 0 || days > parseInt(filters.activity, 10)) return false; break;
                case 'idle30': if (days >= 0 && days < 30) return false; break;   // never counts as idle
                case 'idle90': if (days >= 0 && days < 90) return false; break;
                case 'never':  if (days !== -1) return false; break;
            }

            if (filters.min !== '' && balance < parseFloat(filters.min) - 0.005) return false;
            if (filters.max !== '' && balance > parseFloat(filters.max) + 0.005) return false;
            return true;
        }

        // One search hook per table id, replaced (not stacked) if this script
        // ever runs again for a fresh copy of the page.
        const hook = function(settings, data, dataIndex) {
            if (settings.nTable.id !== 'customersTable') return true;
            const node = table.row(dataIndex).node();
            return node ? rowPasses(node.dataset) : true;
        };
        hook.tableId = 'customersTable';
        $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(f => f.tableId !== 'customersTable');
        $.fn.dataTable.ext.search.push(hook);

        // ---- Cards: totals over exactly the rows the table is showing ------
        const money = n => 'Rs. ' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const plural = (n, one, many) => n + ' ' + (n === 1 ? one : many);
        const card = (key, value, sub) => {
            const v = document.querySelector('[data-card="' + key + '"]');
            const s = document.querySelector('[data-card="' + key + '-sub"]');
            if (v) v.textContent = value;
            if (s) s.textContent = sub || ' ';
        };

        function refreshCards() {
            const rows = table.rows({ search: 'applied' }).nodes().toArray();
            let active = 0, dueSum = 0, dueN = 0, advSum = 0, advN = 0, sales = 0, paid = 0;
            let recent = 0, dormant = 0, never = 0, overN = 0, overSum = 0;
            let topName = '', topBal = 0;

            rows.forEach(tr => {
                const d = tr.dataset;
                const balance = parseFloat(d.balance) || 0;
                const days = parseInt(d.days, 10);
                const credit = parseFloat(d.credit) || 0;

                if (d.status === 'active') active++;
                sales += parseFloat(d.sales) || 0;
                paid += parseFloat(d.paid) || 0;
                if (balance > 0.005) {
                    dueSum += balance; dueN++;
                    if (balance > topBal) { topBal = balance; topName = d.name; }
                    if (credit > 0 && balance > credit + 0.005) { overN++; overSum += balance - credit; }
                } else if (balance < -0.005) {
                    advSum += -balance; advN++;
                }
                if (days >= 0 && days <= 30) recent++;
                if (days === -1) { never++; dormant++; }
                else if (days >= 90) dormant++;
            });

            const n = rows.length;
            card('count', n.toLocaleString('en-US'), active + ' active · ' + (n - active) + ' inactive');
            card('due', money(dueSum), dueN ? plural(dueN, 'customer owes', 'customers owe') : 'Nobody owes anything');
            card('advance', money(advSum), advN ? plural(advN, 'customer', 'customers') + ' paid in advance' : 'No advance payments');
            card('sales', money(sales), 'Collected ' + money(paid));
            card('recent', recent.toLocaleString('en-US'), n ? Math.round(recent / n * 100) + '% of shown customers' : '');
            card('dormant', dormant.toLocaleString('en-US'), never ? never + ' never active' : 'All have bought or paid');
            card('overlimit', overN.toLocaleString('en-US'), overN ? money(overSum) + ' over in total' : 'Nobody is over their limit');
            card('top', money(topBal), topName || 'No customer owes anything');

            const shown = document.getElementById('customersShown');
            if (shown) shown.textContent = n.toLocaleString('en-US');
        }

        // ---- Wire up -------------------------------------------------------
        function syncUrl() {
            const params = new URLSearchParams();
            controls.forEach(el => { if (el.value !== '') params.set(el.dataset.filter, el.value); });
            const qs = params.toString();
            history.replaceState(history.state, '', location.pathname + (qs ? '?' + qs : ''));
        }

        function apply() {
            controls.forEach(el => filters[el.dataset.filter] = el.value);
            table.draw();
            syncUrl();
        }

        controls.forEach(el => {
            el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', apply);
        });

        document.getElementById('resetCustomerFilters').addEventListener('click', function() {
            controls.forEach(el => el.value = '');
            table.search('');
            apply();
        });

        table.on('draw', refreshCards);

        // Restore filters from the URL (?due=due&agent=3 ...) so a filtered
        // view survives a reload / Back / a shared link.
        const params = new URLSearchParams(location.search);
        controls.forEach(el => {
            const v = params.get(el.dataset.filter);
            if (v !== null) {
                el.value = v;
                if (el.tagName === 'SELECT' && el.value !== v) el.value = '';  // option no longer exists
            }
        });
        apply();
    });
</script>
@endpush

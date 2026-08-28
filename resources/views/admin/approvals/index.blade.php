@extends('layouts.admin')

@section('title', 'Pending Approvals')
@section('page-title', 'Pending Approvals')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-xl shadow-card overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <span class="text-sm font-medium text-gray-700">
                <i class="fas fa-shopping-bag text-gray-400 mr-2"></i> Pending Sales / Orders
            </span>
            <span class="ml-2 text-sm text-gray-500">{{ $pendingSales->count() }} pending</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <th class="py-3 px-4">Invoice</th>
                        <th class="py-3 px-4">Customer</th>
                        <th class="py-3 px-4">Submitted By</th>
                        <th class="py-3 px-4">Source</th>
                        <th class="py-3 px-4 text-right">Amount</th>
                        <th class="py-3 px-4 text-right">Pending Payment</th>
                        <th class="py-3 px-4">Submitted</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($pendingSales as $sale)
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-4">
                            <a href="{{ route('admin.sales.show', $sale) }}" class="font-medium text-blue-600 hover:underline">{{ $sale->invoice_no }}</a>
                        </td>
                        <td class="py-3 px-4">{{ $sale->customer->name ?? '-' }}</td>
                        <td class="py-3 px-4">
                            {{ $sale->agent->name ?? ($sale->createdBy->name ?? '-') }}
                        </td>
                        <td class="py-3 px-4">
                            @if($sale->source === 'customer_app')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800"><i class="fas fa-mobile-alt mr-1"></i> Customer App</span>
                            @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-teal-100 text-teal-800"><i class="fas fa-user-tie mr-1"></i> Sales Agent</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-right font-medium">Rs. {{ number_format($sale->total_amount, 2) }}</td>
                        <td class="py-3 px-4 text-right">
                            @php $pending = $sale->payments->where('status', 'pending')->sum('amount'); @endphp
                            @if($pending > 0)
                            <span class="text-yellow-700 font-medium">Rs. {{ number_format($pending, 2) }}</span>
                            @else
                            <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-gray-500">{{ $sale->created_at->format('d-m-Y H:i') }}</td>
                        <td class="py-3 px-4">
                            <div class="flex justify-end gap-2">
                                <form action="{{ route('admin.sales.confirm', $sale) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 bg-green-600 text-white text-xs rounded-lg hover:bg-green-700 transition-colors duration-200">
                                        <i class="fas fa-check mr-1"></i> Confirm
                                    </button>
                                </form>
                                <form action="{{ route('admin.sales.reject', $sale) }}" method="POST" onsubmit="return confirm('Reject this sale/order?');">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 bg-red-600 text-white text-xs rounded-lg hover:bg-red-700 transition-colors duration-200">
                                        <i class="fas fa-times mr-1"></i> Reject
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center py-10 text-gray-400"><i class="fas fa-check-circle text-green-400 text-2xl block mb-2"></i> No pending sales or orders</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-card overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <span class="text-sm font-medium text-gray-700">
                <i class="fas fa-money-bill-wave text-gray-400 mr-2"></i> Pending Payments
            </span>
            <span class="ml-2 text-sm text-gray-500">{{ $pendingPayments->count() }} pending</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <th class="py-3 px-4">Invoice</th>
                        <th class="py-3 px-4">Customer</th>
                        <th class="py-3 px-4">Submitted By</th>
                        <th class="py-3 px-4 text-right">Amount</th>
                        <th class="py-3 px-4">Method</th>
                        <th class="py-3 px-4">Submitted</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($pendingPayments as $payment)
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-4">
                            <a href="{{ route('admin.sales.show', $payment->sale) }}" class="font-medium text-blue-600 hover:underline">{{ $payment->sale->invoice_no ?? '-' }}</a>
                        </td>
                        <td class="py-3 px-4">{{ $payment->sale->customer->name ?? '-' }}</td>
                        <td class="py-3 px-4">{{ $payment->createdBy->name ?? '-' }}</td>
                        <td class="py-3 px-4 text-right font-medium">Rs. {{ number_format($payment->amount, 2) }}</td>
                        <td class="py-3 px-4">{{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</td>
                        <td class="py-3 px-4 text-gray-500">{{ $payment->created_at->format('d-m-Y H:i') }}</td>
                        <td class="py-3 px-4">
                            <div class="flex justify-end gap-2">
                                <form action="{{ route('admin.approvals.payments.approve', $payment) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 bg-green-600 text-white text-xs rounded-lg hover:bg-green-700 transition-colors duration-200">
                                        <i class="fas fa-check mr-1"></i> Approve
                                    </button>
                                </form>
                                <form action="{{ route('admin.approvals.payments.reject', $payment) }}" method="POST" onsubmit="return confirm('Reject this payment?');">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 bg-red-600 text-white text-xs rounded-lg hover:bg-red-700 transition-colors duration-200">
                                        <i class="fas fa-times mr-1"></i> Reject
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center py-10 text-gray-400"><i class="fas fa-check-circle text-green-400 text-2xl block mb-2"></i> No pending payments</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

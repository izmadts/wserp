@extends('layouts.agent')

@section('title', 'My Golden Club Customers')
@section('page-title', 'My Golden Club Customers')

@section('content')
<div class="bg-white rounded-xl shadow-card overflow-hidden">
    <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
        <div>
            <span class="text-sm font-medium text-gray-700">
                <i class="fas fa-users text-gray-400 mr-2"></i> My Customers
            </span>
            <span class="ml-2 text-sm text-gray-500">{{ $customers->total() }} total</span>
        </div>
        <a href="{{ route('agent.customers.create') }}"
           class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors duration-200">
            <i class="fas fa-plus mr-1"></i> Register Customer
        </a>
    </div>

    <div class="p-4 sm:p-6">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Code</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Name</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Phone</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Level</th>
                        <th class="text-right text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Points</th>
                        <th class="text-right text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Lifetime Purchase</th>
                        <th class="text-center text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Status</th>
                        <th class="text-center text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($customers as $customer)
                    <tr class="hover:bg-gray-50 transition-colors duration-150">
                        <td class="py-3 px-2">
                            <code class="text-xs bg-gray-100 px-2 py-1 rounded">{{ $customer->code }}</code>
                        </td>
                        <td class="py-3 px-2">
                            <span class="font-medium text-gray-900">{{ $customer->name }}</span>
                        </td>
                        <td class="py-3 px-2 text-sm text-gray-600">{{ $customer->phone ?? '-' }}</td>
                        <td class="py-3 px-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $customer->membership_level == 'platinum' ? 'bg-purple-100 text-purple-800' :
                                   ($customer->membership_level == 'gold' ? 'bg-yellow-100 text-yellow-800' :
                                   'bg-gray-100 text-gray-800') }}">
                                {{ ucfirst($customer->membership_level ?? 'silver') }}
                            </span>
                        </td>
                        <td class="py-3 px-2 text-right font-medium">{{ number_format($customer->loyalty_points ?? 0) }}</td>
                        <td class="py-3 px-2 text-right">Rs. {{ number_format($customer->lifetime_purchase ?? 0, 2) }}</td>
                        <td class="py-3 px-2 text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $customer->otp_verified ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                {{ $customer->otp_verified ? 'Verified' : 'Pending' }}
                            </span>
                        </td>
                        <td class="py-3 px-2 text-center">
                            <a href="{{ route('agent.customers.show', $customer) }}"
                               class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors duration-200">
                                <i class="fas fa-eye text-sm"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-8 text-center text-gray-500">You haven't registered any customers yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $customers->links() }}
        </div>
    </div>
</div>
@endsection

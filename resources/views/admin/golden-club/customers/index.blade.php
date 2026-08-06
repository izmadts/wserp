@extends('layouts.admin')

@section('title', 'Golden Club Customers')
@section('page-title', 'Customer Management')

@section('content')
<div class="bg-white rounded-xl shadow-card overflow-hidden">
    <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
        <div>
            <span class="text-sm font-medium text-gray-700">
                <i class="fas fa-users text-gray-400 mr-2"></i> All Customers
            </span>
            <span class="ml-2 text-sm text-gray-500">{{ $customers->total() }} total</span>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.golden-club.customers.pending-verification') }}"
               class="px-4 py-2 bg-yellow-600 text-white rounded-lg text-sm font-medium hover:bg-yellow-700 transition-colors duration-200">
                <i class="fas fa-check-double mr-1"></i> Pending Verification
            </a>
        </div>
    </div>

    <div class="px-4 sm:px-6 py-3 border-b border-gray-200 bg-gray-50">
        <form method="GET" action="{{ route('admin.golden-club.customers.index') }}" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Membership Level</label>
                <select name="membership_level" onchange="this.form.submit()" class="rounded-lg border-gray-300 text-sm">
                    <option value="all" {{ request('membership_level', 'all') == 'all' ? 'selected' : '' }}>All Levels</option>
                    <option value="silver" {{ request('membership_level') == 'silver' ? 'selected' : '' }}>Silver</option>
                    <option value="gold" {{ request('membership_level') == 'gold' ? 'selected' : '' }}>Gold</option>
                    <option value="platinum" {{ request('membership_level') == 'platinum' ? 'selected' : '' }}>Platinum</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Verification</label>
                <select name="verified" onchange="this.form.submit()" class="rounded-lg border-gray-300 text-sm">
                    <option value="">All</option>
                    <option value="1" {{ request('verified') == '1' ? 'selected' : '' }}>Verified</option>
                    <option value="0" {{ request('verified') == '0' ? 'selected' : '' }}>Pending</option>
                </select>
            </div>
            @if(request('membership_level', 'all') != 'all' || request()->has('verified'))
            <a href="{{ route('admin.golden-club.customers.index') }}" class="text-sm text-gray-500 hover:text-gray-700 pb-2">
                <i class="fas fa-times mr-1"></i> Clear filters
            </a>
            @endif
        </form>
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
                        <th class="text-right text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Total Purchase</th>
                        <th class="text-center text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Entries</th>
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
                        <td class="py-3 px-2 text-right">Rs. {{ number_format($customer->total_purchase ?? 0, 2) }}</td>
                        <td class="py-3 px-2 text-center">{{ $customer->lucky_draw_entries ?? 0 }}</td>
                        <td class="py-3 px-2 text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $customer->otp_verified ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                {{ $customer->otp_verified ? 'Verified' : 'Pending' }}
                            </span>
                        </td>
                        <td class="py-3 px-2 text-center">
                            <div class="flex items-center justify-center space-x-1">
                                <a href="{{ route('admin.golden-club.customers.show', $customer) }}"
                                   class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors duration-200">
                                    <i class="fas fa-eye text-sm"></i>
                                </a>
                                <a href="{{ route('admin.customers.edit', $customer) }}"
                                   class="p-1.5 text-yellow-600 hover:bg-yellow-50 rounded-lg transition-colors duration-200">
                                    <i class="fas fa-edit text-sm"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-8 text-center text-gray-500">No customers found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $customers->appends(request()->query())->links() }}
        </div>
    </div>
</div>
@endsection

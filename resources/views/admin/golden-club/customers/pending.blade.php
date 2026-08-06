@extends('layouts.admin')

@section('title', 'Pending Verification')
@section('page-title', 'Pending Golden Club Verification')

@section('content')
<div class="bg-white rounded-xl shadow-card overflow-hidden">
    <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
        <div>
            <span class="text-sm font-medium text-gray-700">
                <i class="fas fa-check-double text-gray-400 mr-2"></i> Agent-Registered Customers Awaiting Verification
            </span>
            <span class="ml-2 text-sm text-gray-500">{{ $customers->total() }} total</span>
        </div>
        <a href="{{ route('admin.golden-club.customers.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
            <i class="fas fa-arrow-left mr-1"></i> Back to All Customers
        </a>
    </div>

    <div class="p-4 sm:p-6">
        <p class="text-sm text-gray-500 mb-4">
            <i class="fas fa-info-circle mr-1"></i>
            Per the Salesman Rule, an agent earns zero commission on a self-registered customer until that
            customer is verified here.
        </p>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Code</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Name</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Phone</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Registered By</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Registered On</th>
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
                            <a href="{{ route('admin.golden-club.customers.show', $customer) }}" class="font-medium text-blue-600 hover:underline">{{ $customer->name }}</a>
                        </td>
                        <td class="py-3 px-2 text-sm text-gray-600">{{ $customer->phone ?? '-' }}</td>
                        <td class="py-3 px-2 text-sm text-gray-600">{{ $customer->createdByAgent->name ?? '-' }}</td>
                        <td class="py-3 px-2 text-sm text-gray-600">{{ $customer->created_at->format('d-m-Y') }}</td>
                        <td class="py-3 px-2 text-center">
                            <form action="{{ route('admin.golden-club.customers.verify', $customer) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-green-600 text-white rounded-lg text-xs font-medium hover:bg-green-700">
                                    <i class="fas fa-check mr-1"></i> Verify
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-8 text-center text-gray-500">No customers pending verification. 🎉</td>
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

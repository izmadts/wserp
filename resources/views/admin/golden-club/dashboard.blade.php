@extends('layouts.admin')

@section('title', 'Golden Club Dashboard')
@section('page-title', 'Golden Club Dashboard')

@section('content')
<div class="space-y-6">

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-card p-4">
            <div class="flex items-center justify-between">
                <div class="min-w-0">
                    <p class="text-sm text-gray-500">Today's Registrations</p>
                    <p class="text-2xl font-bold text-blue-600 truncate" title="{{ number_format($stats['todays_registrations']) }}">
                        {{ \App\Helpers\NumberHelper::compact($stats['todays_registrations']) }}
                    </p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-user-plus text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-card p-4">
            <div class="flex items-center justify-between">
                <div class="min-w-0">
                    <p class="text-sm text-gray-500">Members by Tier</p>
                    <p class="text-2xl font-bold text-gray-900 truncate" title="{{ number_format($stats['silver'] + $stats['gold'] + $stats['platinum']) }}">
                        {{ \App\Helpers\NumberHelper::compact($stats['silver'] + $stats['gold'] + $stats['platinum']) }}
                    </p>
                    <p class="text-xs text-gray-500 truncate">Silver: {{ $stats['silver'] }} | Gold: {{ $stats['gold'] }} | Platinum: {{ $stats['platinum'] }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-users text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-card p-4">
            <div class="flex items-center justify-between">
                <div class="min-w-0">
                    <p class="text-sm text-gray-500">Points Issued</p>
                    <p class="text-2xl font-bold text-purple-600 truncate" title="{{ number_format($stats['points_issued']) }}">
                        {{ \App\Helpers\NumberHelper::compact($stats['points_issued']) }}
                    </p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-coins text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-card p-4">
            <div class="flex items-center justify-between">
                <div class="min-w-0">
                    <p class="text-sm text-gray-500">Lucky Draw Entries</p>
                    <p class="text-2xl font-bold text-yellow-600 truncate" title="{{ number_format($stats['lucky_draw_entries']) }}">
                        {{ \App\Helpers\NumberHelper::compact($stats['lucky_draw_entries']) }}
                    </p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-trophy text-yellow-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Secondary Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-card p-4 min-w-0">
            <p class="text-sm text-gray-500">New Customers (3+ orders)</p>
            <p class="text-xl font-bold text-gray-900 truncate" title="{{ number_format($stats['new_customers']) }}">
                {{ \App\Helpers\NumberHelper::compact($stats['new_customers']) }}
            </p>
        </div>
        <div class="bg-white rounded-xl shadow-card p-4 min-w-0">
            <a href="{{ route('admin.golden-club.customers.pending-verification') }}" class="block">
                <p class="text-sm text-gray-500">Pending Verification</p>
                <p class="text-xl font-bold text-orange-600 truncate" title="{{ number_format($stats['pending_verification']) }}">
                    {{ \App\Helpers\NumberHelper::compact($stats['pending_verification']) }}
                </p>
            </a>
        </div>
        <div class="bg-white rounded-xl shadow-card p-4 min-w-0">
            <p class="text-sm text-gray-500">Today's Orders</p>
            <p class="text-xl font-bold text-gray-900 truncate" title="{{ number_format($stats['todays_orders']) }}">
                {{ \App\Helpers\NumberHelper::compact($stats['todays_orders']) }}
            </p>
        </div>
        <div class="bg-white rounded-xl shadow-card p-4 min-w-0">
            <p class="text-sm text-gray-500">Today's Recovery</p>
            <p class="text-xl font-bold text-green-600 truncate" title="Rs. {{ number_format($stats['todays_recovery'], 2) }}">
                Rs. {{ \App\Helpers\NumberHelper::compact($stats['todays_recovery']) }}
            </p>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <a href="{{ route('admin.golden-club.customers.index') }}"
           class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-xl shadow-lg p-4 text-white hover:shadow-xl transition-shadow duration-200">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-users text-xl"></i>
                </div>
                <div class="ml-3">
                    <h4 class="text-sm font-semibold">Customer Management</h4>
                    <p class="text-xs text-blue-100">View all customers</p>
                </div>
            </div>
        </a>

        <a href="{{ route('admin.golden-club.lucky-draw.campaigns') }}"
           class="bg-gradient-to-r from-purple-600 to-purple-700 rounded-xl shadow-lg p-4 text-white hover:shadow-xl transition-shadow duration-200">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-trophy text-xl"></i>
                </div>
                <div class="ml-3">
                    <h4 class="text-sm font-semibold">Lucky Draw</h4>
                    <p class="text-xs text-purple-100">Manage campaigns</p>
                </div>
            </div>
        </a>

        <a href="{{ route('admin.golden-club.rewards.index') }}"
           class="bg-gradient-to-r from-green-600 to-green-700 rounded-xl shadow-lg p-4 text-white hover:shadow-xl transition-shadow duration-200">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-gift text-xl"></i>
                </div>
                <div class="ml-3">
                    <h4 class="text-sm font-semibold">Rewards</h4>
                    <p class="text-xs text-green-100">Manage rewards</p>
                </div>
            </div>
        </a>
    </div>

    <!-- Analytics -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow-card overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h4 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-city text-gray-400 mr-2"></i> Top Cities
                </h4>
            </div>
            <div class="p-4">
                @forelse($topCities as $city)
                <div class="flex justify-between items-center py-1.5 border-b border-gray-100 last:border-0">
                    <span class="text-sm text-gray-700">{{ $city->city }}</span>
                    <span class="text-sm font-semibold text-gray-900">{{ $city->total }}</span>
                </div>
                @empty
                <p class="text-center text-gray-500 py-4">No data yet</p>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-card overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h4 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-box text-gray-400 mr-2"></i> Top Products
                </h4>
            </div>
            <div class="p-4">
                @forelse($topProducts as $product)
                <div class="flex justify-between items-center py-1.5 border-b border-gray-100 last:border-0">
                    <span class="text-sm text-gray-700">{{ $product->name }}</span>
                    <span class="text-sm font-semibold text-gray-900">{{ number_format($product->total_quantity) }}</span>
                </div>
                @empty
                <p class="text-center text-gray-500 py-4">No data yet</p>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-card overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h4 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-crown text-gray-400 mr-2"></i> Top Customers
                </h4>
            </div>
            <div class="p-4">
                @forelse($topCustomers as $customer)
                <div class="flex justify-between items-center py-1.5 border-b border-gray-100 last:border-0">
                    <a href="{{ route('admin.golden-club.customers.show', $customer) }}" class="text-sm text-blue-600 hover:underline">{{ $customer->name }}</a>
                    <span class="text-sm font-semibold text-gray-900">Rs. {{ number_format($customer->lifetime_purchase, 0) }}</span>
                </div>
                @empty
                <p class="text-center text-gray-500 py-4">No data yet</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-card overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h4 class="text-lg font-semibold text-gray-900">
                <i class="fas fa-user-tie text-gray-400 mr-2"></i> Top Sales Agents (by Golden Club Customers)
            </h4>
        </div>
        <div class="p-4">
            @forelse($topSalesmen as $agent)
            <div class="flex justify-between items-center py-1.5 border-b border-gray-100 last:border-0">
                <span class="text-sm text-gray-700">{{ $agent->name }}</span>
                <span class="text-sm font-semibold text-gray-900">{{ $agent->agent_customers_count }} customers</span>
            </div>
            @empty
            <p class="text-center text-gray-500 py-4">No data yet</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
